<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormController;
use Pentiminax\UX\DataTables\Dto\AjaxEditFormQueryDto;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use Pentiminax\UX\DataTables\Form\EditFormRenderer;
use Pentiminax\UX\DataTables\Form\EditFormViewHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Twig\Environment;

/**
 * @internal
 */
#[CoversClass(AjaxEditFormController::class)]
final class AjaxEditFormControllerTest extends TestCase
{
    #[Test]
    public function it_returns_rendered_html_when_the_entity_exists(): void
    {
        $entityManager = $this->createEntityManagerWithEntity(new AjaxEditFormControllerFixture());
        $registry      = $this->createRegistry($entityManager);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('createView')
            ->willReturn(new FormView());

        $controller = new AjaxEditFormController(new EditFormViewHandler(
            new EditFormEntityResolver($registry),
            $this->createRenderer($form),
        ));

        $response = $controller(new AjaxEditFormQueryDto(
            entity: AjaxEditFormControllerFixture::class,
            id: '42',
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
        ));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($payload['success']);
        $this->assertSame('<form>ok</form>', $payload['html']);
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

        $registry = $this->createRegistry($entityManager);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $controller = new AjaxEditFormController(new EditFormViewHandler(
            new EditFormEntityResolver($registry),
            new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig),
        ));

        $response = $controller(new AjaxEditFormQueryDto(
            entity: AjaxEditFormControllerFixture::class,
            id: 'missing',
            columns: [],
        ));

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

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $controller = new AjaxEditFormController(new EditFormViewHandler(
            new EditFormEntityResolver($registry),
            new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig),
        ));

        $response = $controller(new AjaxEditFormQueryDto(
            entity: AjaxEditFormControllerFixture::class,
            id: '42',
            columns: [],
        ));

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame(404, $response->getStatusCode());
        $this->assertFalse($payload['success']);
        $this->assertSame('Entity not found.', $payload['message']);
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
        $twig->expects($this->once())
            ->method('render')
            ->with('@DataTables/form/edit_form.html.twig', $this->isType('array'))
            ->willReturn('<form>ok</form>');

        return new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig);
    }
}

final class AjaxEditFormControllerFixture
{
}
