<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Controller\AjaxBulkController;
use Pentiminax\UX\DataTables\Controller\AjaxBulkQueryDto;
use Pentiminax\UX\DataTables\Exception\InvalidBulkSelectionException;
use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\BulkActionRunner;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(AjaxBulkController::class)]
final class AjaxBulkControllerTest extends TestCase
{
    private const string TOKEN_SECRET = 'test-secret';

    /** @var list<object> */
    private array $touched = [];

    #[Test]
    public function it_runs_the_action_over_the_selected_rows(): void
    {
        $first  = new BulkEntityFixture();
        $second = new BulkEntityFixture();

        $controller = $this->createController($this->createDoctrine([1 => $first, 2 => $second]));

        $response = $controller($this->createRequest(), new AjaxBulkQueryDto(
            dataTable: $this->dataTableToken(),
            action: 'approve',
            ids: [1, 2],
        ));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['success' => true, 'processed' => 2, 'skipped' => 0],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR),
        );
        $this->assertSame([$first, $second], $this->touched);
    }

    #[Test]
    public function it_drops_the_identifiers_that_cannot_name_a_row(): void
    {
        $controller = $this->createController($this->createDoctrine([1 => new BulkEntityFixture()]));

        $controller($this->createRequest(), new AjaxBulkQueryDto(
            dataTable: $this->dataTableToken(),
            action: 'approve',
            ids: [1, '', null, ['nested'], 1.5],
        ));

        $this->assertCount(1, $this->touched);
    }

    #[Test]
    public function it_rejects_an_invalid_csrf_token_before_resolving_the_table(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')
            ->with(new CsrfToken(MutationTokenValidator::TOKEN_ID, 'wrong-token'))
            ->willReturn(false);

        $controller = $this->createController($registry, $csrfTokenManager);

        $this->expectException(InvalidCsrfTokenException::class);
        $controller($this->createRequest('wrong-token'), $this->payload());
    }

    #[Test]
    public function it_rejects_a_request_without_the_token_header(): void
    {
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $controller = $this->createController($this->createMock(ManagerRegistry::class), $csrfTokenManager);

        $this->expectException(InvalidCsrfTokenException::class);
        $controller(new Request(), $this->payload());
    }

    #[Test]
    public function it_rejects_a_forged_data_table_token(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $controller = $this->createController($registry);

        $this->expectException(InvalidDataTableTokenException::class);
        $controller($this->createRequest(), new AjaxBulkQueryDto(dataTable: 'forged-token', action: 'approve', ids: [1]));
    }

    #[Test]
    public function it_rejects_an_action_the_table_does_not_declare(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $controller = $this->createController($registry);

        $this->expectException(MutationNotAllowedException::class);
        $controller($this->createRequest(), new AjaxBulkQueryDto(
            dataTable: $this->dataTableToken(),
            action: 'unknown',
            ids: [1],
        ));
    }

    #[Test]
    public function it_rejects_a_denied_static_permission_before_loading_any_entity(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $controller = $this->createController(
            $registry,
            permissionChecker: new AuthorizationChecker($checker),
            dataTable: new GuardedBulkDataTable(),
        );

        $this->expectException(MutationNotAllowedException::class);
        $controller($this->createRequest(), $this->payload());
    }

    #[Test]
    public function it_rejects_a_select_all_on_a_table_limited_to_the_current_page(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $controller = $this->createController($registry, dataTable: new CurrentPageOnlyBulkDataTable());

        $this->expectException(InvalidBulkSelectionException::class);
        $this->expectExceptionMessage('This table only allows selecting one page at a time.');

        $controller($this->createRequest(), new AjaxBulkQueryDto(
            dataTable: $this->dataTableToken(),
            action: 'approve',
            allMatching: true,
        ));
    }

    #[Test]
    public function it_keeps_a_default_authorization_checker_when_constructed_without_one(): void
    {
        $controller = new AjaxBulkController(
            $this->runner($this->createMock(ManagerRegistry::class), new AuthorizationChecker(new TestAuthorizationChecker())),
            new MutationTokenValidator($this->createStub(CsrfTokenManagerInterface::class)),
            $this->registry(),
        );

        $property = new \ReflectionProperty($controller, 'permissionChecker');

        $this->assertInstanceOf(AuthorizationChecker::class, $property->getValue($controller));
    }

    /**
     * @param array<int, BulkEntityFixture> $entities keyed by identifier
     */
    private function createDoctrine(array $entities): ManagerRegistry
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturnCallback(
            static fn (array $criteria): array => array_values(array_intersect_key($entities, array_flip($criteria['id'])))
        );

        $metadata = $this->createStub(ClassMetadata::class);
        $metadata->method('getIdentifier')->willReturn(['id']);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(BulkEntityFixture::class)->willReturn($repository);
        $manager->method('getClassMetadata')->with(BulkEntityFixture::class)->willReturn($metadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(BulkEntityFixture::class)->willReturn($manager);

        return $registry;
    }

    private function createController(
        ManagerRegistry $registry,
        ?CsrfTokenManagerInterface $csrfTokenManager = null,
        ?AuthorizationChecker $permissionChecker = null,
        ?AbstractDataTable $dataTable = null,
    ): AjaxBulkController {
        $permissionChecker ??= new AuthorizationChecker(new TestAuthorizationChecker());

        if (null === $csrfTokenManager) {
            $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
            $csrfTokenManager->method('isTokenValid')->willReturn(true);
        }

        return new AjaxBulkController(
            $this->runner($registry, $permissionChecker),
            new MutationTokenValidator($csrfTokenManager),
            $this->registry($dataTable),
            $permissionChecker,
        );
    }

    private function runner(ManagerRegistry $registry, AuthorizationChecker $permissionChecker): BulkActionRunner
    {
        return new BulkActionRunner(
            new EntityLocator($registry),
            $permissionChecker,
            new MutationFlusher(),
            new NullMercurePublisher(),
            new MercureTopicResolver(),
        );
    }

    private function registry(?AbstractDataTable $dataTable = null): AjaxDataTableRegistry
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('bulk_table')->willReturn($dataTable ?? new BulkEntityFixtureDataTable($this->touched));

        return new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [BulkEntityFixtureDataTable::class => 'bulk_table'],
        );
    }

    private function dataTableToken(): string
    {
        $token = $this->registry()->getActionToken(BulkEntityFixtureDataTable::class);

        $this->assertNotNull($token);

        return $token;
    }

    private function payload(): AjaxBulkQueryDto
    {
        return new AjaxBulkQueryDto(dataTable: $this->dataTableToken(), action: 'approve', ids: [1]);
    }

    private function createRequest(string $token = 'valid-token'): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, $token);

        return $request;
    }
}

final class BulkEntityFixture
{
}

#[AsDataTable(entityClass: BulkEntityFixture::class)]
class BulkEntityFixtureDataTable extends AbstractDataTable
{
    /**
     * @param list<object> $touched entities the handler saw, by reference so the test can read them
     */
    public function __construct(private array &$touched = [])
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        $touched = &$this->touched;

        return $actions->add(BulkAction::new('approve')->handler(static function (BulkRecords $records) use (&$touched): void {
            foreach ($records as $entity) {
                $touched[] = $entity;
            }
        }));
    }
}

#[AsDataTable(entityClass: BulkEntityFixture::class)]
final class GuardedBulkDataTable extends BulkEntityFixtureDataTable
{
    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions->add(BulkAction::new('approve')->setPermission('ORDER_APPROVE'));
    }
}

#[AsDataTable(entityClass: BulkEntityFixture::class)]
final class CurrentPageOnlyBulkDataTable extends BulkEntityFixtureDataTable
{
    public function configureBulkActions(BulkActions $actions): BulkActions
    {
        return $actions->selectCurrentPageOnly()->add(BulkAction::new('approve'));
    }
}
