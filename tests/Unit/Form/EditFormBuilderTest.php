<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @internal
 */
class EditFormBuilderTest extends TestCase
{
    public function test_build_form_adds_mapped_columns(): void
    {
        $entity  = new \stdClass();
        $columns = [
            ['name' => 'name', 'title' => 'Name', 'type' => 'string'],
            ['name' => 'price', 'title' => 'Price', 'type' => 'num'],
        ];

        $form        = $this->createMock(FormInterface::class);
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);

        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with(FormType::class, $entity)
            ->willReturn($formBuilder);

        $formBuilder->expects($this->exactly(2))
            ->method('add')
            ->willReturnSelf();

        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($form);

        $builder = new EditFormBuilder($formFactory, new ColumnToFormTypeMapper());
        $result  = $builder->buildForm($entity, $columns);

        $this->assertSame($form, $result);
    }

    public function test_build_form_skips_action_columns(): void
    {
        $entity  = new \stdClass();
        $columns = [
            ['name' => 'name', 'title' => 'Name', 'type' => 'string'],
            ['name' => 'actions', 'title' => 'Actions', 'actions' => [['type' => 'DELETE']]],
        ];

        $form        = $this->createMock(FormInterface::class);
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);

        $formFactory->method('createBuilder')->willReturn($formBuilder);

        $formBuilder->expects($this->once())
            ->method('add')
            ->with('name', TextType::class, $this->anything())
            ->willReturnSelf();

        $formBuilder->method('getForm')->willReturn($form);

        $builder = new EditFormBuilder($formFactory, new ColumnToFormTypeMapper());
        $builder->buildForm($entity, $columns);
    }

    public function test_build_form_skips_columns_without_name(): void
    {
        $entity  = new \stdClass();
        $columns = [
            ['title' => 'Name', 'type' => 'string'],
            ['name' => '', 'title' => 'Empty', 'type' => 'string'],
        ];

        $form        = $this->createMock(FormInterface::class);
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);

        $formFactory->method('createBuilder')->willReturn($formBuilder);

        $formBuilder->expects($this->never())
            ->method('add');

        $formBuilder->method('getForm')->willReturn($form);

        $builder = new EditFormBuilder($formFactory, new ColumnToFormTypeMapper());
        $builder->buildForm($entity, $columns);
    }

    public function test_build_form_disables_identifier_fields(): void
    {
        $entity  = new \stdClass();
        $columns = [
            ['name' => 'id', 'title' => 'ID', 'type' => 'num'],
            ['name' => 'name', 'title' => 'Name', 'type' => 'string'],
        ];

        $form        = $this->createMock(FormInterface::class);
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);

        $formFactory->method('createBuilder')->willReturn($formBuilder);

        $addCalls = [];
        $formBuilder->expects($this->exactly(2))
            ->method('add')
            ->willReturnCallback(function (string $name, string $type, array $options) use (&$addCalls, $formBuilder) {
                $addCalls[$name] = $options;

                return $formBuilder;
            });

        $formBuilder->method('getForm')->willReturn($form);

        $builder = new EditFormBuilder($formFactory, new ColumnToFormTypeMapper());
        $builder->buildForm($entity, $columns, ['id']);

        $this->assertTrue($addCalls['id']['disabled']);
        $this->assertArrayNotHasKey('disabled', $addCalls['name']);
    }
}
