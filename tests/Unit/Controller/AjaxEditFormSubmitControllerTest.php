<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\EditModalTemplateResolverInterface;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormSubmitController;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use Pentiminax\UX\DataTables\Form\EditFormService;
use Pentiminax\UX\DataTables\Form\EditModalRenderer;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * @internal
 */
#[CoversClass(AjaxEditFormSubmitController::class)]
final class AjaxEditFormSubmitControllerTest extends TestCase
{
    #[Test]
    public function it_returns_rendered_html_when_the_form_is_invalid(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new AjaxEditFormSubmitControllerFixture(), 1);
        $entityManager->expects($this->never())->method('flush');

        $registry = $this->createRegistry($entityManager);
        $form     = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('submit')
            ->with(['name' => 'Alice']);
        $form->expects($this->once())
            ->method('isValid')
            ->willReturn(false);

        [$formFactory, $renderer, $templateResolver] = $this->createFormFactoryRendererAndResolver($form, '<form>invalid</form>', 1, true, 'SomeDataTable');

        $controller = new AjaxEditFormSubmitController(new EditFormService(
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
        ));

        $response = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: 'SomeDataTable',
        ));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('<form>invalid</form>', $payload['html']);
    }

    #[Test]
    public function it_returns_success_when_the_form_is_valid(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new AjaxEditFormSubmitControllerFixture(), 1);
        $entityManager->expects($this->once())->method('flush');

        $registry = $this->createRegistry($entityManager);
        $form     = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('submit')
            ->with(['name' => 'Alice']);
        $form->expects($this->once())
            ->method('isValid')
            ->willReturn(true);
        $form->expects($this->never())->method('createView');

        [$formFactory, $renderer, $templateResolver] = $this->createFormFactoryRendererAndResolver($form, '', 1, false, 'SomeDataTable');

        $controller = new AjaxEditFormSubmitController(new EditFormService(
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
        ));

        $response = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: 'SomeDataTable',
        ));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
    }

    #[Test]
    public function it_only_publishes_mercure_updates_when_topics_are_provided(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new AjaxEditFormSubmitControllerFixture(), 2);
        $entityManager->expects($this->exactly(2))->method('flush');

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->exactly(2))
            ->method('getManagerForClass')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($entityManager);

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/topic/42']             === $update->getTopics()
                    && '{"type":"edit","id":42}' === $update->getData();
            }))
            ->willReturn('urn:uuid:published');

        $forms = [
            $this->createValidFormMock(),
            $this->createValidFormMock(),
        ];

        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->exactly(2))
            ->method('add')
            ->with('name', $this->isType('string'), $this->isType('array'))
            ->willReturnSelf();
        $formBuilder->expects($this->exactly(2))
            ->method('getForm')
            ->willReturnOnConsecutiveCalls(...$forms);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->exactly(2))
            ->method('createBuilder')
            ->with($this->isType('string'), $this->isType('object'))
            ->willReturn($formBuilder);

        $renderer = $this->createMock(EditModalRenderer::class);
        $renderer->expects($this->never())->method('render');
        $renderer->expects($this->never())->method('renderBody');

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->never())->method('resolveChromeTemplate');
        $templateResolver->expects($this->never())->method('resolveBodyTemplate');
        $templateResolver->expects($this->exactly(2))
            ->method('resolveColumns')
            ->with('SomeDataTable')
            ->willReturn([TextColumn::new('name', 'Name')]);

        $controller = new AjaxEditFormSubmitController(new EditFormService(
            new EditFormEntityResolver($registry),
            new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()),
            $renderer,
            $templateResolver,
            new MercureUpdatePublisher($hub),
        ));

        $responseWithoutTopics = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            dataTableClass: 'SomeDataTable',
        ));

        $responseWithTopics = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            formData: ['name' => 'Alice'],
            topics: ['/topic/42'],
            dataTableClass: 'SomeDataTable',
        ));

        $payloadWithoutTopics = json_decode((string) $responseWithoutTopics->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $payloadWithTopics    = json_decode((string) $responseWithTopics->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertTrue($payloadWithoutTopics['success']);
        $this->assertTrue($payloadWithTopics['success']);
    }

    private function createRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($entityManager);

        return $registry;
    }

    private function createEntityManagerWithEntity(object $entity, int $calls): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->exactly($calls))
            ->method('find')
            ->with(42)
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->exactly($calls))
            ->method('getIdentifierFieldNames')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly($calls))
            ->method('getRepository')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->exactly($calls))
            ->method('getClassMetadata')
            ->with(AjaxEditFormSubmitControllerFixture::class)
            ->willReturn($classMetadata);

        return $entityManager;
    }

    /**
     * @return array{FormFactoryInterface, EditModalRenderer, EditModalTemplateResolverInterface}
     */
    private function createFormFactoryRendererAndResolver(FormInterface $form, string $html, int $calls, bool $expectInvalidRender, ?string $dataTableClass = null): array
    {
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->exactly($calls))
            ->method('add')
            ->with('name', $this->isType('string'), $this->isType('array'))
            ->willReturnSelf();
        $formBuilder->expects($this->exactly($calls))
            ->method('getForm')
            ->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->exactly($calls))
            ->method('createBuilder')
            ->with($this->isType('string'), $this->isType('object'))
            ->willReturn($formBuilder);

        $renderer = $this->createMock(EditModalRenderer::class);
        if ($expectInvalidRender) {
            $renderer->expects($this->once())->method('renderBody')->with($this->isType('object'))->willReturn($html);
        } else {
            $renderer->expects($this->never())->method('render');
            $renderer->expects($this->never())->method('renderBody');
        }

        $templateResolver = $this->createMock(EditModalTemplateResolverInterface::class);
        $templateResolver->expects($this->exactly($calls))
            ->method('resolveColumns')
            ->with($dataTableClass)
            ->willReturn([TextColumn::new('name', 'Name')]);

        if ($expectInvalidRender) {
            $templateResolver->expects($this->exactly($calls))
                ->method('resolveChromeTemplate')
                ->with($dataTableClass)
                ->willReturn('modal.html.twig');
            $templateResolver->expects($this->exactly($calls))
                ->method('resolveBodyTemplate')
                ->willReturn('body.html.twig');
        } else {
            $templateResolver->expects($this->never())->method('resolveChromeTemplate');
            $templateResolver->expects($this->never())->method('resolveBodyTemplate');
        }

        return [$formFactory, $renderer, $templateResolver];
    }

    private function createValidFormMock(): FormInterface
    {
        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('submit')
            ->with(['name' => 'Alice']);
        $form->expects($this->once())
            ->method('isValid')
            ->willReturn(true);

        return $form;
    }
}

final class AjaxEditFormSubmitControllerFixture
{
}
