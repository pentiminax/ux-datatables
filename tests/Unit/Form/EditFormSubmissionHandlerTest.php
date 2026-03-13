<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormRequestDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use Pentiminax\UX\DataTables\Form\EditFormRenderer;
use Pentiminax\UX\DataTables\Form\EditFormSubmissionHandler;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Psr\Log\LoggerInterface;
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
#[CoversClass(EditFormSubmissionHandler::class)]
final class EditFormSubmissionHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_not_found_when_entity_cannot_be_resolved(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormSubmissionHandlerFixture::class)
            ->willReturn(null);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $handler = new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig),
        );

        $result = $handler->handle(new AjaxEditFormRequestDto(
            entity: EditFormSubmissionHandlerFixture::class,
            id: 404,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame('Entity not found.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_returns_rendered_html_when_the_form_is_invalid(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new EditFormSubmissionHandlerFixture());
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

        $handler = new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            $this->createRenderer($form),
        );

        $result = $handler->handle(new AjaxEditFormRequestDto(
            entity: EditFormSubmissionHandlerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
        ));

        $this->assertFalse($result->success);
        $this->assertSame('<form>invalid</form>', $result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_flushes_and_publishes_updates_when_the_form_is_valid(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new EditFormSubmissionHandlerFixture());
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

        $hub = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->with($this->callback(function (Update $update) {
                return ['/topic/42']             === $update->getTopics()
                    && '{"type":"edit","id":42}' === $update->getData();
            }))
            ->willReturn('urn:uuid:edit');

        $handler = new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            $this->createRenderer($form),
            new MercureUpdatePublisher($hub),
        );

        $result = $handler->handle(new AjaxEditFormRequestDto(
            entity: EditFormSubmissionHandlerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
            topics: ['/topic/42'],
        ));

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    #[Test]
    public function it_returns_success_when_mercure_publish_fails_after_flush(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new EditFormSubmissionHandlerFixture());
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

        $exception = new \RuntimeException('Mercure hub unavailable.');
        $hub       = $this->createMock(HubInterface::class);
        $hub->expects($this->once())
            ->method('publish')
            ->willThrowException($exception);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error');

        $handler = new EditFormSubmissionHandler(
            new EditFormEntityResolver($registry),
            $this->createRenderer($form),
            new MercureUpdatePublisher($hub, $logger),
        );

        $result = $handler->handle(new AjaxEditFormRequestDto(
            entity: EditFormSubmissionHandlerFixture::class,
            id: 42,
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
            formData: ['name' => 'Alice'],
            topics: ['/topic/42'],
        ));

        $this->assertTrue($result->success);
        $this->assertNull($result->html);
        $this->assertSame('', $result->message);
    }

    private function createRegistry(EntityManagerInterface $entityManager): ManagerRegistry
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormSubmissionHandlerFixture::class)
            ->willReturn($entityManager);

        return $registry;
    }

    private function createEntityManagerWithEntity(object $entity): EntityManagerInterface
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with(42)
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn([]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(EditFormSubmissionHandlerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(EditFormSubmissionHandlerFixture::class)
            ->willReturn($classMetadata);

        return $entityManager;
    }

    private function createRenderer(FormInterface $form): EditFormRenderer
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
        if ($form instanceof FormInterface) {
            $twig->method('render')->willReturn('<form>invalid</form>');
        }

        return new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig);
    }
}

final class EditFormSubmissionHandlerFixture
{
}
