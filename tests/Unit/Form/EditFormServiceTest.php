<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\DBAL\Driver\Exception as DriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;
use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use Pentiminax\UX\DataTables\Form\EditModalTemplateResolver;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\MutationContext;
use Pentiminax\UX\DataTables\Mutation\MutationFlusher;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Tests\Fixtures\Security\TestAuthorizationChecker;
use Pentiminax\UX\DataTables\Tests\Support\BuildsUnscopedRowScopeGuard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
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

/**
 * Topic resolution itself is covered by MercureTopicResolverTest; here the
 * resolver is a stub and only the publish/flush/permission behavior matters.
 *
 * @internal
 */
#[CoversClass(EditFormService::class)]
final class EditFormServiceTest extends TestCase
{
    use BuildsUnscopedRowScopeGuard;
    /**
     * The topics the injected topic resolver produces.
     */
    private const TOPICS = ['/topic/42'];

    #[Test]
    public function it_renders_the_form_for_a_resolved_entity_on_view(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerWithEntity();
        $form          = $this->createStub(FormInterface::class);

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with($this->renderRequestFor($form, $entity))
            ->willReturn('<div>ok</div>');
        $renderer->expects($this->never())->method('renderBody');

        $service = new EditFormService(
            new EntityLocator(null),
            new EditFormBuilder($this->createFormFactory($form, $entity), new ColumnToFormTypeMapper()),
            $renderer,
            $this->createRenderingTemplateResolver(EditFormServiceFixtureDataTable::class),
            new NullMercurePublisher(),
            new MercureTopicResolver(),
            new MutationFlusher(),
            $this->unscopedRowScopeGuard(),
            new AuthorizationChecker(new TestAuthorizationChecker()),
        );

        $result = $service->renderForm($this->row($entity, $entityManager));

        $this->assertTrue($result->success);
        $this->assertSame('<div>ok</div>', $result->html);
        $this->assertSame('', $result->message);
        $this->assertSame(200, $result->statusCode);
    }

    #[Test]
    public function it_returns_rendered_html_without_flushing_when_the_form_is_invalid_on_submit(): void
    {
        $entity        = new EditFormServiceFixture();
        $entityManager = $this->createEntityManagerWithEntity();
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
            new EntityLocator(null),
            new EditFormBuilder($this->createFormFactory($form, $entity), new ColumnToFormTypeMapper()),
            $renderer,
            $this->createRenderingTemplateResolver(EditFormServiceFixtureDataTable::class),
            new NullMercurePublisher(),
            new MercureTopicResolver(),
            new MutationFlusher(),
            $this->unscopedRowScopeGuard(),
            new AuthorizationChecker(new TestAuthorizationChecker()),
        );

        $result = $service->submitForm($this->row($entity, $entityManager), ['name' => 'Alice']);

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

    /**
     * Regression: the submit flush used to be unguarded, so a unique constraint
     * violation surfaced as a 500 leaking SQL instead of the 409 the delete and
     * inline-edit paths already return.
     */
    #[Test]
    public function it_maps_a_unique_constraint_violation_on_submit_to_a_persistence_exception(): void
    {
        $driverException = new class('constraint violation') extends \RuntimeException implements DriverException {
            public function getSQLState(): ?string
            {
                return '23505';
            }
        };
        $previous = new UniqueConstraintViolationException($driverException, null);

        $caught = null;

        try {
            $this->handleValidSubmit(new NullMercurePublisher(), $previous);
        } catch (MutationPersistenceException $exception) {
            $caught = $exception;
        }

        $this->assertInstanceOf(MutationPersistenceException::class, $caught);
        $this->assertSame(409, $caught->getStatusCode());
        $this->assertSame($previous, $caught->getPrevious());
    }

    private function row(object $entity, EntityManagerInterface $entityManager): AuthorizedRow
    {
        $dataTable = new EditFormServiceFixtureDataTable();

        return new AuthorizedRow(
            table: new ResolvedDataTable($dataTable, EditFormServiceFixture::class, $dataTable::class),
            action: null,
            context: new MutationContext($entity, $entityManager),
            id: 42,
        );
    }

    /**
     * Submits a valid form and returns the result; the modal must never be re-rendered.
     *
     * When $flushFailure is given, the persistence layer rejects the flush instead.
     */
    private function handleValidSubmit(MercurePublisherInterface $publisher, ?\Throwable $flushFailure = null): AjaxActionResult
    {
        $dataTable      = new EditFormServiceFixtureDataTable();
        $dataTableClass = $dataTable::class;
        $entity         = new EditFormServiceFixture();
        $entityManager  = $this->createEntityManagerWithEntity($entity, 42);
        $flush          = $entityManager->expects($this->once())->method('flush');

        if (null !== $flushFailure) {
            $flush->willThrowException($flushFailure);
        }

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
            new EntityLocator(null),
            new EditFormBuilder($this->createFormFactory($form, $entity), new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            $publisher,
            $this->topicResolverReturning(self::TOPICS),
            new MutationFlusher(),
            $this->unscopedRowScopeGuard(),
            new AuthorizationChecker(new TestAuthorizationChecker()),
        );

        return $service->submitForm($this->row($entity, $entityManager), ['name' => 'Alice']);
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

    private function createEntityManagerWithEntity(): EntityManagerInterface
    {
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(EditFormServiceFixture::class)
            ->willReturn($classMetadata);

        return $entityManager;
    }

    /**
     * @param string[] $topics
     */
    private function topicResolverReturning(array $topics): MercureTopicResolver
    {
        $topicResolver = $this->createStub(MercureTopicResolver::class);
        $topicResolver->method('resolveConfig')->willReturn(new MercureConfig($topics));

        return $topicResolver;
    }
}

final class EditFormServiceFixture
{
    public string $name = '';
}

#[AsDataTable(entityClass: EditFormServiceFixture::class)]
class EditFormServiceFixtureDataTable extends AbstractDataTable
{
    public function __construct()
    {
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault());
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::edit());
    }
}
