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
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormController;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(AjaxEditFormController::class)]
final class AjaxEditFormControllerTest extends TestCase
{
    private const string TOKEN_SECRET = 'test-secret';

    #[Test]
    public function it_returns_rendered_html_when_the_entity_exists(): void
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->once())
            ->method('add')
            ->with('name', $this->isType('string'), $this->isType('array'))
            ->willReturnSelf();
        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($this->createMock(FormInterface::class));

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with($this->isType('string'), $this->isType('object'))
            ->willReturn($formBuilder);

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($this->isType('object'))
            ->willReturn('<div>ok</div>');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->once())->method('resolveChromeTemplate')->willReturn('modal.html.twig');
        $templateResolver->expects($this->once())->method('resolveBodyTemplate')->willReturn('body.html.twig');
        $templateResolver->expects($this->once())
            ->method('resolveColumns')
            ->willReturn([TextColumn::new('name', 'Name')]);

        $controller = $this->controller(
            $this->createRegistry($this->createEntityManagerWithEntity(new AjaxEditFormControllerFixture())),
            $formFactory,
            $renderer,
            $templateResolver,
            $this->registeredDataTables(),
        );

        $response = $controller($this->payload('42'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('<div>ok</div>', $payload['html']);
    }

    #[Test]
    public function it_returns_not_found_when_the_entity_is_missing(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with('missing')
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AjaxEditFormControllerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->never())->method('getClassMetadata');

        [$formFactory, $renderer, $templateResolver] = $this->createUnusedFormCollaborators();

        $controller = $this->controller($this->createRegistry($entityManager), $formFactory, $renderer, $templateResolver);

        $response = $controller($this->payload('missing'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Entity not found.', $payload['message']);
    }

    #[Test]
    public function it_returns_not_found_when_the_manager_is_missing(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(AjaxEditFormControllerFixture::class)
            ->willReturn(null);

        [$formFactory, $renderer, $templateResolver] = $this->createUnusedFormCollaborators();

        $controller = $this->controller($registry, $formFactory, $renderer, $templateResolver);

        $response = $controller($this->payload('42'));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Entity not found.', $payload['message']);
    }

    private function controller(
        ManagerRegistry $registry,
        FormFactoryInterface $formFactory,
        EditModalRenderer $renderer,
        EditModalTemplateResolverInterface $templateResolver,
        ?ContainerInterface $dataTables = null,
    ): AjaxEditFormController {
        $authorizationChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);

        return new AjaxEditFormController(
            new EditFormService(
                new EntityLocator($registry),
                new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
                $renderer,
                $templateResolver,
                new NullMercurePublisher(),
                dataTables: $dataTables,
                permissionChecker: new PermissionChecker($authorizationChecker),
            ),
            $this->tableRegistry(),
        );
    }

    private function tableRegistry(): AjaxDataTableRegistry
    {
        $locator = $this->createMock(ContainerInterface::class);
        $locator->method('get')->with('edit_form_table')->willReturn(new AjaxEditFormControllerDataTable());

        return new AjaxDataTableRegistry(
            $locator,
            new AjaxDataTableTokenManager(self::TOKEN_SECRET),
            [AjaxEditFormControllerDataTable::class => 'edit_form_table'],
        );
    }

    private function payload(string $id): AjaxEditFormQueryDto
    {
        $token = $this->tableRegistry()->getActionToken(AjaxEditFormControllerDataTable::class);

        $this->assertNotNull($token);

        return new AjaxEditFormQueryDto(dataTable: $token, id: $id);
    }

    /**
     * @return array{FormFactoryInterface, EditModalRenderer, EditModalTemplateResolverInterface}
     */
    private function createUnusedFormCollaborators(): array
    {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveColumns');

        return [$formFactory, $renderer, $templateResolver];
    }

    private function registeredDataTables(): ContainerInterface
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(AjaxEditFormControllerDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(AjaxEditFormControllerDataTable::class)->willReturn(new AjaxEditFormControllerDataTable());

        return $dataTables;
    }

    private function createRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(AjaxEditFormControllerFixture::class)
            ->willReturn($entityManager);

        return $registry;
    }

    private function createEntityManagerWithEntity(object $entity): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with('42')
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(AjaxEditFormControllerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(AjaxEditFormControllerFixture::class)
            ->willReturn($classMetadata);

        return $entityManager;
    }
}

final class AjaxEditFormControllerFixture
{
}

#[AsDataTable(entityClass: AjaxEditFormControllerFixture::class)]
final class AjaxEditFormControllerDataTable extends AbstractDataTable
{
    public function __construct()
    {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault());
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
