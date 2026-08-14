<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(EditFormService::class)]
final class EditFormServiceTest extends TestCase
{
    #[Test]
    public function it_returns_not_found_when_entity_cannot_be_resolved_on_view(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormServiceFixture::class)
            ->willReturn(null);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
        );

        $result = $service->handleView(new AjaxEditFormQueryDto(
            entity: EditFormServiceFixture::class,
            id: '404',
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame('Entity not found.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_renders_the_form_for_a_resolved_entity_on_view(): void
    {
        $entity = new EditFormServiceFixture();

        $entityManager = $this->createEntityManagerWithEntity($entity, '42');
        $registry      = $this->createRegistry($entityManager);
        $form          = $this->createMock(FormInterface::class);

        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->once())
            ->method('add')
            ->with('name', $this->isType('string'), $this->isType('array'))
            ->willReturnSelf();
        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with($this->isType('string'), $entity)
            ->willReturn($formBuilder);

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($this->callback(function (EditModalRenderRequest $request) use ($entity, $form) {
                return $request->form    === $form
                    && $request->entity  === $entity
                    && 'table.html.twig' === $request->templatePath
                    && 'body.html.twig'  === $request->bodyTemplatePath;
            }))
            ->willReturn('<div>ok</div>');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->once())
            ->method('resolveChromeTemplate')
            ->with(EditFormServiceFixtureDataTable::class)
            ->willReturn('table.html.twig');
        $templateResolver->expects($this->once())
            ->method('resolveBodyTemplate')
            ->willReturn('body.html.twig');
        $templateResolver->expects($this->once())
            ->method('resolveColumns')
            ->with(EditFormServiceFixtureDataTable::class)
            ->willReturn([TextColumn::new('name', 'Name')]);

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
        );

        $result = $service->handleView(new AjaxEditFormQueryDto(
            entity: EditFormServiceFixture::class,
            id: '42',
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertTrue($result->success);
        $this->assertSame('<div>ok</div>', $result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_returns_not_found_when_entity_cannot_be_resolved_on_submit(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormServiceFixture::class)
            ->willReturn(null);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 404,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame('Entity not found.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_returns_rendered_html_when_the_form_is_invalid_on_submit(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerWithEntity($entity, 42);
        $entityManager->expects($this->never())->method('flush');
        $registry = $this->createRegistry($entityManager);
        $form     = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(false);

        [$formFactory, $renderer, $templateResolver] = $this->createFormFactoryRendererAndResolver(
            form: $form,
            entity: $entity,
            renderedHtml: '<form>invalid</form>',
            expectRenderRequest: true,
            dataTableClass: EditFormServiceFixtureDataTable::class,
        );

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame('<form>invalid</form>', $result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_flushes_and_publishes_updates_when_the_form_is_valid(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new EditFormServiceFixture(), 42);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createRegistry($entityManager);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(true);
        $form->expects($this->never())->method('createView');

        [$formFactory, $renderer, $templateResolver] = $this->createFormFactoryRendererAndResolver(
            form: $form,
            entity: new EditFormServiceFixture(),
            renderedHtml: '',
            expectRenderRequest: false,
            dataTableClass: EditFormServiceFixtureDataTable::class,
        );

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/topic/42']             === $update->getTopics()
                    && '{"type":"edit","id":42}' === $update->getData();
            }))
            ->willReturn('urn:uuid:edit');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub),
            $this->resolverReturning(['/topic/42']),
            $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_returns_success_when_mercure_publish_fails_after_flush(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new EditFormServiceFixture(), 42);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createRegistry($entityManager);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(true);
        $form->expects($this->never())->method('createView');

        [$formFactory, $renderer, $templateResolver] = $this->createFormFactoryRendererAndResolver(
            form: $form,
            entity: new EditFormServiceFixture(),
            renderedHtml: '',
            expectRenderRequest: false,
            dataTableClass: EditFormServiceFixtureDataTable::class,
        );

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('Mercure hub unavailable.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub, $logger),
            $this->resolverReturning(['/topic/42']),
            $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_publishes_the_datatables_own_mercure_topics_instead_of_the_bare_resolver_ones(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new EditFormServiceFixture(), 42);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createRegistry($entityManager);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(true);
        $form->expects($this->never())->method('createView');

        [$formFactory, $renderer, $templateResolver] = $this->createFormFactoryRendererAndResolver(
            form: $form,
            entity: new EditFormServiceFixture(),
            renderedHtml: '',
            expectRenderRequest: false,
            dataTableClass: EditFormServiceMercureFixtureDataTable::class,
        );

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/datatable-instance/topic'] === $update->getTopics()
                    && '{"type":"edit","id":42}'     === $update->getData();
            }))
            ->willReturn('urn:uuid:edit');

        // The bare entity-class resolver would publish a *different* topic;
        // it must not be consulted once the DataTable instance resolves.
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->expects($this->never())->method('resolveMercureConfig');

        $hubUrlResolver = $this->createMock(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $dataTable = new EditFormServiceMercureFixtureDataTable($hubUrlResolver);

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EditFormServiceMercureFixtureDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(EditFormServiceMercureFixtureDataTable::class)->willReturn($dataTable);

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub),
            $resolver,
            $dataTables,
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceMercureFixtureDataTable::class,
        ));

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_rejects_submit_when_the_datatable_class_is_not_registered(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EditFormServiceFixtureDataTable::class)->willReturn(false);
        $dataTables->expects($this->never())->method('get');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $dataTables,
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_rejects_view_when_the_datatable_class_is_not_registered(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EditFormServiceFixtureDataTable::class)->willReturn(false);
        $dataTables->expects($this->never())->method('get');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $dataTables,
        );

        $result = $service->handleView(new AjaxEditFormQueryDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_rejects_submit_when_no_datatable_locator_is_available(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_rejects_view_when_no_datatable_locator_is_available(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
        );

        $result = $service->handleView(new AjaxEditFormQueryDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_rejects_view_when_edit_is_not_granted_on_the_entity(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            permissionChecker: $this->denyingChecker('EDIT', $entity),
        );

        $result = $service->handleView(new AjaxEditFormQueryDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_rejects_submit_when_edit_is_not_granted_on_the_entity(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            permissionChecker: $this->denyingChecker('EDIT', $entity),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_rejects_submit_when_the_datatable_entity_class_does_not_match(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerThatFinds($entity, 42);
        $registry      = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');
        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('renderBody');
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $dataTable  = new EditFormServiceMismatchedEntityDataTable();
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EditFormServiceMismatchedEntityDataTable::class)->willReturn(true);
        $dataTables->method('get')->with(EditFormServiceMismatchedEntityDataTable::class)->willReturn($dataTable);

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $dataTables,
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: EditFormServiceMismatchedEntityDataTable::class,
        ));

        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_returns_bad_request_when_data_table_class_is_missing_on_view(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $formFactory      = $this->createMock(FormFactoryInterface::class);
        $renderer         = $this->createMock(EditModalRenderer::class);
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
        );

        $result = $service->handleView(new AjaxEditFormQueryDto(
            entity: EditFormServiceFixture::class,
            id: '1',
        ));

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_returns_bad_request_when_data_table_class_is_missing_on_submit(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $formFactory      = $this->createMock(FormFactoryInterface::class);
        $renderer         = $this->createMock(EditModalRenderer::class);
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 1,
            formData: ['name' => 'Alice'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertNull($result->html);
    }

    private function registeredDataTables(string $dataTableClass, object $dataTable): ContainerInterface
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with($dataTableClass)->willReturn(true);
        $dataTables->method('get')->with($dataTableClass)->willReturn($dataTable);

        return $dataTables;
    }

    private function denyingChecker(string $attribute, object $subject): PermissionChecker
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with($attribute, $subject)->willReturn(false);

        return new PermissionChecker($checker);
    }

    private function createEntityManagerThatFinds(object $entity, int|string $id): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($entity);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(EditFormServiceFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->never())->method('getClassMetadata');
        $entityManager->expects($this->never())->method('flush');

        return $entityManager;
    }

    private function createRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormServiceFixture::class)
            ->willReturn($entityManager);

        return $registry;
    }

    /**
     * @param string[] $topics
     */
    private function resolverReturning(array $topics): MercureConfigResolverInterface
    {
        $resolver = $this->createMock(MercureConfigResolverInterface::class);
        $resolver->method('resolveMercureConfig')
            ->with(EditFormServiceFixture::class)
            ->willReturn(new MercureConfig(topics: $topics, hubUrl: 'https://hub.example/.well-known/mercure'));

        return $resolver;
    }

    private function createEntityManagerWithEntity(object $entity, int|string $id): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with($id)
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(EditFormServiceFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(EditFormServiceFixture::class)
            ->willReturn($classMetadata);

        return $entityManager;
    }

    /**
     * @return array{FormFactoryInterface, EditModalRenderer, EditModalTemplateResolverInterface}
     */
    private function createFormFactoryRendererAndResolver(
        FormInterface $form,
        object $entity,
        string $renderedHtml,
        bool $expectRenderRequest,
        ?string $dataTableClass = null,
    ): array {
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->once())
            ->method('add')
            ->with('name', $this->isType('string'), $this->isType('array'))
            ->willReturnSelf();
        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with($this->isType('string'), $this->isType('object'))
            ->willReturn($formBuilder);

        $renderer = $this->createMock(EditModalRenderer::class);

        if ($expectRenderRequest) {
            $renderer->expects($this->once())
                ->method('renderBody')
                ->with($this->callback(function (EditModalRenderRequest $request) use ($form, $entity) {
                    return $request->form    === $form
                        && $request->entity  === $entity
                        && 'table.html.twig' === $request->templatePath
                        && 'body.html.twig'  === $request->bodyTemplatePath;
                }))
                ->willReturn($renderedHtml);
        } else {
            $renderer->expects($this->never())->method('render');
            $renderer->expects($this->never())->method('renderBody');
        }

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->once())
            ->method('resolveColumns')
            ->with($dataTableClass)
            ->willReturn([TextColumn::new('name', 'Name')]);

        if ($expectRenderRequest) {
            $templateResolver->expects($this->once())
                ->method('resolveChromeTemplate')
                ->with($dataTableClass)
                ->willReturn('table.html.twig');
            $templateResolver->expects($this->once())
                ->method('resolveBodyTemplate')
                ->willReturn('body.html.twig');
        } else {
            $templateResolver->expects($this->never())->method('resolveChromeTemplate');
            $templateResolver->expects($this->never())->method('resolveBodyTemplate');
        }

        return [$formFactory, $renderer, $templateResolver];
    }
}

final class EditFormServiceFixture
{
}

#[AsDataTable(entityClass: EditFormServiceFixture::class)]
final class EditFormServiceFixtureDataTable extends AbstractDataTable
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

#[AsDataTable(entityClass: EditFormServiceFixture::class, mercure: true)]
final class EditFormServiceMercureFixtureDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?MercureHubUrlResolverInterface $mercureHubUrlResolver = null,
    ) {
        parent::__construct();
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
            ->mercure(topics: ['/datatable-instance/topic']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class EditFormServiceMismatchedEntityDataTable extends AbstractDataTable
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
