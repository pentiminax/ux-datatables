<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Ajax\DetailRowService;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Controller\AjaxDetailController;
use Pentiminax\UX\DataTables\Controller\AjaxEntityQueryDto;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRowActionGate;
use Pentiminax\UX\DataTables\Tests\Support\BuildsUnscopedRowScopeGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[CoversClass(AjaxDetailController::class)]
final class AjaxDetailControllerTest extends TestCase
{
    use BuildsRowActionGate;
    use BuildsUnscopedRowScopeGuard;
    private const TOKEN_SECRET = 'test-secret';

    #[Test]
    public function it_returns_the_rendered_detail_row(): void
    {
        $controller = $this->controller($this->registryReturning(new AjaxDetailControllerFixture('alice@example.com')), true);

        $response = $controller(new Request(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 7));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('Email: alice@example.com', $payload['html']);
    }

    #[Test]
    public function it_returns_forbidden_when_view_is_not_granted(): void
    {
        $controller = $this->controller($this->registryReturning(new AjaxDetailControllerFixture('alice@example.com')), false);

        try {
            $controller(new Request(), new AjaxEntityQueryDto(dataTable: $this->dataTableToken(), id: 7));
            $this->fail('A denied detail row must be refused.');
        } catch (MutationNotAllowedException $exception) {
            $response = $this->clientResponse($exception);
        }

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(AjaxActionResult::forbidden()->toJsonResponse()->getContent(), $response->getContent());
    }

    private function controller(ManagerRegistry $registry, bool $granted): AjaxDetailController
    {
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($granted);

        $service = new DetailRowService(
            new EntityLocator($registry),
            $this->unscopedRowScopeGuard(),
            new Environment(new ArrayLoader(['detail.html.twig' => 'Email: {{ entity.email }}'])),
            new AuthorizationChecker($authorizationChecker),
        );

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);

        return new AjaxDetailController($service, $this->rowActionGate($this->tableRegistry(), $registry, $csrf, $this->unscopedRowScopeGuard(), new AuthorizationChecker($authorizationChecker)));
    }

    private function registryReturning(AjaxDetailControllerFixture $entity): ManagerRegistry
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('find')->with(7)->willReturn($entity);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getRepository')->with(AjaxDetailControllerFixture::class)->willReturn($repository);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->with(AjaxDetailControllerFixture::class)->willReturn($manager);

        return $registry;
    }

    private function tableRegistry(): AjaxDataTableRegistry
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('detail_table')->willReturn(new AjaxDetailControllerDataTable());

        return new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [AjaxDetailControllerDataTable::class => 'detail_table'],
        );
    }

    private function dataTableToken(): string
    {
        $token = $this->tableRegistry()->getActionToken(AjaxDetailControllerDataTable::class);

        $this->assertNotNull($token);

        return $token;
    }
}

final class AjaxDetailControllerFixture
{
    public function __construct(public string $email)
    {
    }
}

#[AsDataTable(entityClass: AjaxDetailControllerFixture::class)]
final class AjaxDetailControllerDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('email', 'Email');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::detail()->collapsible('detail.html.twig'));
    }
}
