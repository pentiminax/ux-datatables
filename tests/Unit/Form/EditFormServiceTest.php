<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use Pentiminax\UX\DataTables\Form\EditModalTemplateResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\TestCase;
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
 * Topic resolution itself is covered by MercureTopicResolverTest; here the
 * resolver is a stub and only the publish/flush/permission behavior matters.
 *
 * @internal
 */
#[CoversClass(EditFormService::class)]
final class EditFormServiceTest extends TestCase
{
    /**
     * The topics the injected topic resolver produces.
     */
    private const TOPICS = ['/topic/42'];

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

        $result = $this->handleWithoutFormCollaborators(handler: $handler, registry: $registry);

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
            new MercureTopicResolver(),
        );

        $result = $service->handleView($this->resolved(new EditFormServiceFixtureDataTable()), '42');

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
            new MercureTopicResolver(),
        );

        $result = $service->handleSubmit($this->resolved(new EditFormServiceFixtureDataTable()), 42, ['name' => 'Alice']);

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
                return self::TOPICS              === $update->getTopics()
                    && '{"type":"edit","id":42}' === $update->getData();
            }))
            ->willReturn('urn:uuid:edit');

        $result = $this->handleValidSubmit(new MercureUpdatePublisher($hub));

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

        $result = $this->handleValidSubmit(new MercureUpdatePublisher($hub, $logger));

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
            permissionChecker: new PermissionChecker($checker),
        ));
    }

    private function assertForbidden(AjaxActionResult $result): void
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
        ?PermissionChecker $permissionChecker = null,
    ): AjaxActionResult {
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $renderer->expects($this->never())->method('renderBody');

        $templateResolver = $this->createMock(EditModalTemplateResolver::class);
        $templateResolver->expects($this->never())->method('resolveColumns');
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveBodyTemplate');

        $service = new EditFormService(
            new EntityLocator($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new NullMercurePublisher(),
            new MercureTopicResolver(),
            $permissionChecker,
        );

        $dataTable = $this->resolved(new EditFormServiceFixtureDataTable());

        if ('view' === $handler) {
            return $service->handleView($dataTable, 42);
        }

        return $service->handleSubmit($dataTable, 42, ['name' => 'Alice']);
    }

    private function resolved(AbstractDataTable $dataTable): ResolvedDataTable
    {
        return new ResolvedDataTable($dataTable, EditFormServiceFixture::class, $dataTable::class);
    }

    /**
     * Submits a valid form and returns the result; the modal must never be re-rendered.
     */
    private function handleValidSubmit(MercurePublisherInterface $publisher): AjaxActionResult
    {
        $dataTable      = new EditFormServiceFixtureDataTable();
        $dataTableClass = $dataTable::class;
        $entity         = new EditFormServiceFixture();
        $entityManager  = $this->createEntityManagerWithEntity($entity, 42);
        $entityManager->expects($this->once())->method('flush');

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())->method('submit')->with(['name' => 'Alice']);
        $form->expects($this->once())->method('isValid')->willReturn(true);
        $form->expects($this->never())->method('createView');

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $renderer->expects($this->never())->method('renderBody');

        $templateResolver = $this->createMock(EditModalTemplateResolver::class);
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
            $this->topicResolverReturning(self::TOPICS),
        );

        return $service->handleSubmit($this->resolved($dataTable), 42, ['name' => 'Alice']);
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

    private function createRenderingTemplateResolver(string $dataTableClass): EditModalTemplateResolver
    {
        $templateResolver = $this->createMock(EditModalTemplateResolver::class);
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
    private function topicResolverReturning(array $topics): MercureTopicResolver
    {
        $topicResolver = $this->createStub(MercureTopicResolver::class);
        $topicResolver->method('resolve')->willReturn($topics);

        return $topicResolver;
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
