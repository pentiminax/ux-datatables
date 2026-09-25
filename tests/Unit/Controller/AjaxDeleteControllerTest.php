<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Controller\AjaxEntityQueryDto;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\InvalidCsrfTokenException;
use Pentiminax\UX\DataTables\Exception\InvalidDataTableTokenException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * @internal
 */
#[CoversClass(AjaxDeleteController::class)]
final class AjaxDeleteControllerTest extends TestCase
{
    private const string TOKEN_SECRET = 'test-secret';

    #[Test]
    public function it_removes_the_entity_and_returns_success(): void
    {
        $entity   = new DeletableEntityFixture();
        $registry = $this->createDoctrine(12, $entity);

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/server/deletable-entity-fixtures/{id}'], ['type' => 'delete', 'id' => 12]);

        $topicResolver = $this->createStub(MercureTopicResolver::class);
        $topicResolver->method('resolve')->willReturn(['/server/deletable-entity-fixtures/{id}']);

        $mutator = new EntityMutator(new EntityLocator($registry), $this->createStub(PropertyAccessorInterface::class), $publisher, new AuthorizationChecker(new TestAuthorizationChecker()), $topicResolver, new MutationFlusher());

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')
            ->with(new CsrfToken(MutationTokenValidator::TOKEN_ID, 'valid-token'))
            ->willReturn(true);

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager), $this->registry(), new AuthorizationChecker(new TestAuthorizationChecker()));

        $response = $controller($this->createRequest(), new AjaxEntityQueryDto(
            dataTable: $this->dataTableToken(),
            id: 12,
        ));

        $this->assertSame(200, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertTrue($payload['success']);
    }

    #[Test]
    public function it_derives_the_data_table_class_from_the_token_and_publishes_its_own_mercure_topics(): void
    {
        $registry = $this->createDoctrine(12, new DeletableEntityFixture());

        $publisher = $this->createMock(MercurePublisherInterface::class);
        $publisher->expects($this->once())
            ->method('publish')
            ->with(['/manual/deletable-entity-fixtures'], ['type' => 'delete', 'id' => 12]);

        // The bare entity-class resolver would publish a *different* topic;
        // it must not be consulted once the token's data table resolves.
        $resolver = $this->createMock(MercureConfigResolver::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createStub(MercureHubUrlResolver::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $dataTable = new DeletableEntityFixtureDataTable($hubUrlResolver);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(DeletableEntityFixtureDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(DeletableEntityFixtureDataTable::class)->willReturn($dataTable);

        $mutator = new EntityMutator(
            new EntityLocator($registry),
            $this->createStub(PropertyAccessorInterface::class),
            $publisher,
            new AuthorizationChecker(new TestAuthorizationChecker()),
            new MercureTopicResolver($resolver, $dataTables),
            new MutationFlusher(),
        );

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = new AjaxDeleteController($mutator, new MutationTokenValidator($csrfTokenManager), $this->registry($dataTable), new AuthorizationChecker(new TestAuthorizationChecker()));

        $controller($this->createRequest(), new AjaxEntityQueryDto(
            dataTable: $this->dataTableToken(),
            id: 12,
        ));
    }

    #[Test]
    public function it_rejects_the_request_and_does_not_delete_when_the_csrf_token_is_invalid(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(false);

        $controller = $this->createController($registry, $csrfTokenManager);

        $this->expectException(InvalidCsrfTokenException::class);
        $controller($this->createRequest('wrong-token'), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 12));
    }

    #[Test]
    public function it_rejects_the_request_and_does_not_delete_when_the_token_header_is_missing(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->expects($this->never())->method('isTokenValid');

        $controller = $this->createController($registry, $csrfTokenManager);

        $this->expectException(InvalidCsrfTokenException::class);
        $controller(new Request(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 12));
    }

    #[Test]
    public function it_lets_a_missing_entity_bubble_as_an_exception(): void
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = $this->createController($this->createDoctrine(404, null), $csrfTokenManager);

        $this->expectException(EntityNotFoundException::class);
        $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 404));
    }

    #[Test]
    public function it_rejects_missing_delete_action_before_touching_the_entity(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = $this->createController($registry, $csrfTokenManager, new DeleteActionMissingDataTable());

        $this->expectException(MutationNotAllowedException::class);
        $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 12));
    }

    #[Test]
    public function it_rejects_static_delete_action_denial_before_touching_the_entity(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $controller = $this->createController(
            $registry,
            $csrfTokenManager,
            new StaticDeniedDeleteActionDataTable(),
            new AuthorizationChecker($checker),
        );

        $this->expectException(MutationNotAllowedException::class);
        $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 12));
    }

    /**
     * Built without a checker -- an application with no SecurityBundle -- the controller must
     * still delete, even behind an action carrying an application permission: the bundle's own
     * DT_EXECUTE_ACTION is granted when there is no firewall to vote on it.
     */
    #[Test]
    public function it_still_deletes_when_constructed_without_an_authorization_checker(): void
    {
        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = new AjaxDeleteController(
            new EntityMutator(
                new EntityLocator($this->createDoctrine(12, new DeletableEntityFixture())),
                $this->createStub(PropertyAccessorInterface::class),
                new NullMercurePublisher(),
                new AuthorizationChecker(),
                new MercureTopicResolver(),
                new MutationFlusher(),
            ),
            new MutationTokenValidator($csrfTokenManager),
            $this->registry(new StaticDeniedDeleteActionDataTable()),
        );

        $response = $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 12));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['success']);
    }

    #[Test]
    public function it_rejects_row_scoped_delete_action_denial_before_mutation(): void
    {
        $entity = new DeletableEntityFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('find')->with(12)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(DeletableEntityFixture::class)->willReturn($repository);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DeletableEntityFixture::class)->willReturn($manager);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $attribute, mixed $subject = null): bool => Permission::DT_DELETE_ROW === $attribute
                || ($subject instanceof ActionPermissionContext && !$subject->hasRowContext)
        );

        $controller = $this->createController(
            $registry,
            $csrfTokenManager,
            new RowDeniedDeleteActionDataTable(),
            new AuthorizationChecker($checker),
        );

        $this->expectException(MutationNotAllowedException::class);
        $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 12));
    }

    #[Test]
    public function it_rejects_an_unknown_data_table_token_before_touching_the_entity(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = $this->createController($registry, $csrfTokenManager);

        $this->expectException(InvalidDataTableTokenException::class);
        $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: 'forged-token', id: 12));
    }

    /**
     * Doctrine wiring resolving $entity for $id, asserting the removal happens exactly
     * once, or never at all when the entity is missing.
     */
    private function createDoctrine(int $id, ?DeletableEntityFixture $entity): ManagerRegistry
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with($id)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(DeletableEntityFixture::class)->willReturn($repository);

        if (null === $entity) {
            $manager->expects($this->never())->method('remove');
            $manager->expects($this->never())->method('flush');
        } else {
            $manager->expects($this->once())->method('remove')->with($entity);
            $manager->expects($this->once())->method('flush');
        }

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(DeletableEntityFixture::class)->willReturn($manager);

        return $registry;
    }

    /**
     * Controller wired on a mutator that publishes nothing, for tests that only assert
     * on the token validation and on the absence of any entity lookup.
     */
    private function createController(
        ManagerRegistry $registry,
        CsrfTokenManagerInterface $csrfTokenManager,
        ?AbstractDataTable $dataTable = null,
        ?AuthorizationChecker $permissionChecker = null,
    ): AjaxDeleteController {
        $permissionChecker ??= new AuthorizationChecker(new TestAuthorizationChecker());

        return new AjaxDeleteController(
            new EntityMutator(
                new EntityLocator($registry),
                $this->createStub(PropertyAccessorInterface::class),
                new NullMercurePublisher(),
                $permissionChecker,
                new MercureTopicResolver(),
                new MutationFlusher(),
            ),
            new MutationTokenValidator($csrfTokenManager),
            $this->registry($dataTable),
            $permissionChecker,
        );
    }

    private function registry(?AbstractDataTable $dataTable = null): AjaxDataTableRegistry
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('deletable_table')->willReturn($dataTable ?? new DeletableEntityFixtureDataTable());

        return new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [DeletableEntityFixtureDataTable::class => 'deletable_table'],
        );
    }

    #[Test]
    public function it_refuses_a_read_token_replayed_on_the_action_route(): void
    {
        $doctrine = $this->createMock(ManagerRegistry::class);
        $doctrine->expects($this->never())->method('getManagerForClass');

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        $controller = $this->createController($doctrine, $csrfTokenManager);

        $this->expectException(InvalidDataTableTokenException::class);

        $controller($this->createRequest(), new AjaxEntityQueryDto(dataTable: $this->readTableToken(), id: 12));
    }

    private function readTableToken(): string
    {
        $token = $this->registry()->getToken(DeletableEntityFixtureDataTable::class);

        $this->assertNotNull($token);

        return $token;
    }

    private function dataTableToken(): string
    {
        $token = $this->registry()->getActionToken(DeletableEntityFixtureDataTable::class);

        $this->assertNotNull($token);

        return $token;
    }

    private function createRequest(string $token = 'valid-token'): Request
    {
        $request = new Request();
        $request->headers->set(MutationTokenValidator::HEADER, $token);

        return $request;
    }
}

final class DeletableEntityFixture
{
}

#[AsDataTable(entityClass: DeletableEntityFixture::class, mercure: true)]
class DeletableEntityFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolver $mercureHubUrlResolver = null,
    ) {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            renderingPreparer: new RenderingPreparer(
                mercureHubUrlResolver: $this->mercureHubUrlResolver,
            )
        ));
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->mercure(topics: ['/manual/deletable-entity-fixtures']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::delete());
    }
}

#[AsDataTable(entityClass: DeletableEntityFixture::class, mercure: true)]
final class DeleteActionMissingDataTable extends DeletableEntityFixtureDataTable
{
    public function configureActions(Actions $actions): Actions
    {
        return $actions;
    }
}

#[AsDataTable(entityClass: DeletableEntityFixture::class, mercure: true)]
final class StaticDeniedDeleteActionDataTable extends DeletableEntityFixtureDataTable
{
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::delete()->setPermission('DELETE_BOOK'));
    }
}

#[AsDataTable(entityClass: DeletableEntityFixture::class, mercure: true)]
final class RowDeniedDeleteActionDataTable extends DeletableEntityFixtureDataTable
{
    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::delete()->setPermission('DELETE_BOOK', static fn (object $entity): object => $entity));
    }
}
