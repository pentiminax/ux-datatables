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
use Pentiminax\UX\DataTables\Form\EditFormResult;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
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
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
    #[TestWith(['view'])]
    #[TestWith(['submit'])]
    public function it_returns_not_found_when_the_entity_cannot_be_resolved(string $handler): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormServiceFixture::class)
            ->willReturn(null);

        $result = $this->handleWithoutFormCollaborators(
            handler: $handler,
            registry: $registry,
            dataTableClass: EditFormServiceFixtureDataTable::class,
            dataTables: $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
        );

        $this->assertFalse($result->success);
        $this->assertSame(404, $result->statusCode);
        $this->assertSame('Entity not found.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_renders_the_form_for_a_resolved_entity_on_view(): void
    {
        $entity = new EditFormServiceFixture();
        $form   = $this->createMock(FormInterface::class);

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($this->renderRequestFor($form, $entity))
            ->willReturn('<div>ok</div>');
        $renderer->expects($this->never())->method('renderBody');

        $service = new EditFormService(
            new EntityLocator($this->createRegistry($this->createEntityManagerWithEntity($entity, '42'))),
            new EditFormBuilder($this->createFormFactory($form, $entity), new ColumnToFormTypeMapper()),
            $renderer,
            $this->createRenderingTemplateResolver(EditFormServiceFixtureDataTable::class),
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
        $this->assertSame(200, $result->statusCode);
    }

    #[Test]
    public function it_returns_rendered_html_without_flushing_when_the_form_is_invalid_on_submit(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerWithEntity($entity, 42);
        $entityManager->expects($this->never())->method('flush');

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(false);

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->once())
            ->method('renderBody')
            ->with($this->renderRequestFor($form, $entity))
            ->willReturn('<form>invalid</form>');
        $renderer->expects($this->never())->method('render');

        $service = new EditFormService(
            new EntityLocator($this->createRegistry($entityManager)),
            new EditFormBuilder($this->createFormFactory($form, $entity), new ColumnToFormTypeMapper()),
            $renderer,
            $this->createRenderingTemplateResolver(EditFormServiceFixtureDataTable::class),
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
        $this->assertSame(200, $result->statusCode);
    }

    #[Test]
    public function it_flushes_and_publishes_updates_when_the_form_is_valid(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/topic/42']             === $update->getTopics()
                    && '{"type":"edit","id":42}' === $update->getData();
            }))
            ->willReturn('urn:uuid:edit');

        $result = $this->handleValidSubmit(
            publisher: new MercureUpdatePublisher($hub),
            mercureConfigResolver: $this->resolverReturning(['/topic/42']),
            dataTables: $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
            dataTableClass: EditFormServiceFixtureDataTable::class,
        );

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_returns_success_when_mercure_publish_fails_after_flush(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willThrowException(new \RuntimeException('Mercure hub unavailable.'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $result = $this->handleValidSubmit(
            publisher: new MercureUpdatePublisher($hub, $logger),
            mercureConfigResolver: $this->resolverReturning(['/topic/42']),
            dataTables: $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
            dataTableClass: EditFormServiceFixtureDataTable::class,
        );

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_publishes_the_datatables_own_mercure_topics_instead_of_the_bare_resolver_ones(): void
    {
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

        $hubUrlResolver = $this->createStub(MercureHubUrlResolverInterface::class);
        $hubUrlResolver->method('resolveHubUrl')->willReturn('https://hub.example/.well-known/mercure');

        $result = $this->handleValidSubmit(
            publisher: new MercureUpdatePublisher($hub),
            mercureConfigResolver: $resolver,
            dataTables: $this->registeredDataTables(
                EditFormServiceMercureFixtureDataTable::class,
                new EditFormServiceMercureFixtureDataTable($hubUrlResolver),
            ),
            dataTableClass: EditFormServiceMercureFixtureDataTable::class,
        );

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    #[TestWith(['view'])]
    #[TestWith(['submit'])]
    public function it_rejects_when_edit_is_not_granted_on_the_entity(string $handler): void
    {
        $entity = new EditFormServiceFixture();

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->with('EDIT', $entity)->willReturn(false);

        $this->assertForbidden($this->handleWithoutFormCollaborators(
            handler: $handler,
            registry: $this->createRegistry($this->createEntityManagerThatFinds($entity, 42)),
            dataTableClass: EditFormServiceFixtureDataTable::class,
            dataTables: $this->registeredDataTables(EditFormServiceFixtureDataTable::class, new EditFormServiceFixtureDataTable()),
            permissionChecker: new PermissionChecker($checker),
        ));
    }

    #[Test]
    #[TestWith(['view'])]
    #[TestWith(['submit'])]
    public function it_rejects_when_the_datatable_class_is_not_registered(string $handler): void
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with(EditFormServiceFixtureDataTable::class)->willReturn(false);
        $dataTables->expects($this->never())->method('get');

        $this->assertForbidden($this->handleWithoutFormCollaborators(
            handler: $handler,
            registry: $this->createRegistry($this->createEntityManagerThatFinds(new EditFormServiceFixture(), 42)),
            dataTableClass: EditFormServiceFixtureDataTable::class,
            dataTables: $dataTables,
        ));
    }

    #[Test]
    #[TestWith(['view'])]
    #[TestWith(['submit'])]
    public function it_rejects_when_no_datatable_locator_is_available(string $handler): void
    {
        $this->assertForbidden($this->handleWithoutFormCollaborators(
            handler: $handler,
            registry: $this->createRegistry($this->createEntityManagerThatFinds(new EditFormServiceFixture(), 42)),
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));
    }

    #[Test]
    #[TestWith(['view'])]
    #[TestWith(['submit'])]
    public function it_rejects_when_the_datatable_entity_class_does_not_match(string $handler): void
    {
        $this->assertForbidden($this->handleWithoutFormCollaborators(
            handler: $handler,
            registry: $this->createRegistry($this->createEntityManagerThatFinds(new EditFormServiceFixture(), 42)),
            dataTableClass: EditFormServiceMismatchedEntityDataTable::class,
            dataTables: $this->registeredDataTables(
                EditFormServiceMismatchedEntityDataTable::class,
                new EditFormServiceMismatchedEntityDataTable(),
            ),
        ));
    }

    #[Test]
    #[TestWith(['view'])]
    #[TestWith(['submit'])]
    public function it_returns_bad_request_when_the_datatable_class_is_missing(string $handler): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->never())->method('getManagerForClass');

        $result = $this->handleWithoutFormCollaborators(
            handler: $handler,
            registry: $registry,
            dataTableClass: null,
        );

        $this->assertFalse($result->success);
        $this->assertSame(400, $result->statusCode);
        $this->assertSame('Edit modal requires a DataTable class (AbstractDataTable).', $result->message);
        $this->assertNull($result->html);
    }

    private function assertForbidden(EditFormResult $result): void
    {
        $this->assertFalse($result->success);
        $this->assertSame(403, $result->statusCode);
        $this->assertSame('You are not allowed to perform this action.', $result->message);
        $this->assertNull($result->html);
    }

    /**
     * Runs the requested handler against a service whose form, render and template
     * collaborators must never be reached.
     */
    private function handleWithoutFormCollaborators(
        string $handler,
        ManagerRegistry $registry,
        ?string $dataTableClass,
        ?ContainerInterface $dataTables = null,
        ?PermissionChecker $permissionChecker = null,
    ): EditFormResult {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $renderer->expects($this->never())->method('renderBody');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveColumns');
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveBodyTemplate');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            dataTables: $dataTables,
            permissionChecker: $permissionChecker,
        );

        if ('view' === $handler) {
            return $service->handleView(new AjaxEditFormQueryDto(
                entity: EditFormServiceFixture::class,
                id: 42,
                dataTableClass: $dataTableClass,
            ));
        }

        return $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: $dataTableClass,
        ));
    }

    /**
     * Submits a valid form and returns the result; the modal must never be re-rendered.
     */
    private function handleValidSubmit(
        MercurePublisherInterface $publisher,
        MercureConfigResolverInterface $mercureConfigResolver,
        ContainerInterface $dataTables,
        string $dataTableClass,
    ): EditFormResult {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerWithEntity($entity, 42);
        $entityManager->expects($this->once())->method('flush');

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(true);
        $form->expects($this->never())->method('createView');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $renderer->expects($this->never())->method('renderBody');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->once())
            ->method('resolveColumns')
            ->with($dataTableClass)
            ->willReturn([TextColumn::new('name', 'Name')]);
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveBodyTemplate');

        $service = new EditFormService(
            new EntityLocator($this->createRegistry($entityManager)),
            new EditFormBuilder($this->createFormFactory($form, $entity), new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            $publisher,
            $mercureConfigResolver,
            $dataTables,
        );

        return $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: $dataTableClass,
        ));
    }

    private function renderRequestFor(FormInterface $form, object $entity): Callback
    {
        return $this->callback(function (EditModalRenderRequest $request) use ($form, $entity) {
            return $request->form    === $form
                && $request->entity  === $entity
                && 'table.html.twig' === $request->templatePath
                && 'body.html.twig'  === $request->bodyTemplatePath;
        });
    }

    private function createFormFactory(FormInterface $form, object $entity): FormFactoryInterface
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->once())
            ->method('add')
            ->with('name', TextType::class, ['label' => 'Name'])
            ->willReturnSelf();
        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with(FormType::class, $entity)
            ->willReturn($formBuilder);

        return $formFactory;
    }

    private function createRenderingTemplateResolver(string $dataTableClass): EditModalTemplateResolverInterface
    {
        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->once())
            ->method('resolveColumns')
            ->with($dataTableClass)
            ->willReturn([TextColumn::new('name', 'Name')]);
        $templateResolver->expects($this->once())
            ->method('resolveChromeTemplate')
            ->with($dataTableClass)
            ->willReturn('table.html.twig');
        $templateResolver->expects($this->once())
            ->method('resolveBodyTemplate')
            ->willReturn('body.html.twig');

        return $templateResolver;
    }

    private function registeredDataTables(string $dataTableClass, object $dataTable): ContainerInterface
    {
        $dataTables = $this->createMock(ContainerInterface::class);
        $dataTables->method('has')->with($dataTableClass)->willReturn(true);
        $dataTables->method('get')->with($dataTableClass)->willReturn($dataTable);

        return $dataTables;
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
