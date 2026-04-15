<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Form\EditModalRenderRequest;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            topics: ['/topic/42'],
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub, $logger),
        );

        $result = $service->handleSubmit(new AjaxEditFormRequestDto(
            entity: EditFormServiceFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            topics: ['/topic/42'],
            dataTableClass: EditFormServiceFixtureDataTable::class,
        ));

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
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
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
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

    private function createRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormServiceFixture::class)
            ->willReturn($entityManager);

        return $registry;
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

final class EditFormServiceFixtureDataTable
{
}
