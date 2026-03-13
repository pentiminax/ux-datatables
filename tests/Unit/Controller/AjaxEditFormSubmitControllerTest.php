<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormSubmitController;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use Pentiminax\UX\DataTables\Form\EditFormRenderer;
use Pentiminax\UX\DataTables\Form\EditFormSubmissionHandler;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Twig\Environment;

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
        $form->expects($this->once())
            ->method('createView')
            ->willReturn(new FormView());

        $controller = new AjaxEditFormSubmitController(new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            $this->createRenderer($form, '<form>invalid</form>'),
        ));

        $response = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
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

        $controller = new AjaxEditFormSubmitController(new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            $this->createRenderer($form, '<form>unused</form>'),
        ));

        $response = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
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

        $controller = new AjaxEditFormSubmitController(new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            $this->createRendererSequence(),
            new MercureUpdatePublisher($hub),
        ));

        $responseWithoutTopics = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
        ));

        $responseWithTopics = $controller(new AjaxEditFormRequestDto(
            entity: AjaxEditFormSubmitControllerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
            topics: ['/topic/42'],
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

    private function createRenderer(FormInterface $form, string $html): EditFormRenderer
    {
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

        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturn($html);

        return new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig);
    }

    private function createRendererSequence(): EditFormRenderer
    {
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

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        return new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig);
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
        $form->expects($this->never())->method('createView');

        return $form;
    }
}

final class AjaxEditFormSubmitControllerFixture
{
}
