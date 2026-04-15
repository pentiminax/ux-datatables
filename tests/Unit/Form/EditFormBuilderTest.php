<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\TestCase;
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
            TextColumn::new('name', 'Name'),
            NumberColumn::new('price', 'Price'),
        ];

        $form        = $this->createMock(FormInterface::class);
        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);

        $formFactory->expects($this->once())
            ->method('createBuilder')
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
            TextColumn::new('name', 'Name'),
            ActionColumn::fromActions('actions', 'Actions', new Actions([])),
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

    public function test_build_form_disables_identifier_fields(): void
    {
        $entity  = new \stdClass();
        $columns = [
            NumberColumn::new('id', 'ID'),
            TextColumn::new('name', 'Name'),
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
