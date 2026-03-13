<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
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
#[CoversClass(EditFormViewHandler::class)]
final class EditFormViewHandlerTest extends TestCase
{
    #[Test]
    public function it_returns_not_found_when_entity_cannot_be_resolved(): void
    {
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormViewHandlerFixture::class)
            ->willReturn(null);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->never())->method('createBuilder');

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->never())->method('render');

        $handler = new EditFormViewHandler(
            new EditFormEntityResolver($registry),
            new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig),
        );

        $result = $handler->handle(new AjaxEditFormQueryDto(
            entity: EditFormViewHandlerFixture::class,
            id: '404',
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
        ));

        $this->assertFalse($result->success);
        $this->assertSame('Entity not found.', $result->message);
        $this->assertNull($result->html);
    }

    #[Test]
    public function it_renders_the_form_for_a_resolved_entity(): void
    {
        $entity = new EditFormViewHandlerFixture();

        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('find')
            ->with('42')
            ->willReturn($entity);

        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->expects($this->once())
            ->method('getIdentifierFieldNames')
            ->willReturn(['id']);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())
            ->method('getRepository')
            ->with(EditFormViewHandlerFixture::class)
            ->willReturn($repository);
        $entityManager->expects($this->once())
            ->method('getClassMetadata')
            ->with(EditFormViewHandlerFixture::class)
            ->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects($this->once())
            ->method('getManagerForClass')
            ->with(EditFormViewHandlerFixture::class)
            ->willReturn($entityManager);

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->once())
            ->method('createView')
            ->willReturn(new FormView());

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

        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@DataTables/form/edit_form.html.twig', $this->isType('array'))
            ->willReturn('<form>ok</form>');

        $handler = new EditFormViewHandler(
            new EditFormEntityResolver($registry),
            new EditFormRenderer(new EditFormBuilder($formFactory, new ColumnToFormTypeMapper()), $twig),
        );

        $result = $handler->handle(new AjaxEditFormQueryDto(
            entity: EditFormViewHandlerFixture::class,
            id: '42',
            columns: [['name' => 'name', 'title' => 'Name', 'type' => 'string']],
        ));

        $this->assertTrue($result->success);
        $this->assertSame('<form>ok</form>', $result->html);
        $this->assertSame('', $result->message);
    }
}

final class EditFormViewHandlerFixture
{
}
