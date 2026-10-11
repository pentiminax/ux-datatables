<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\Forms;

/**
 * @internal
 */
#[CoversClass(EditFormBuilder::class)]
final class EditFormBuilderTest extends TestCase
{
    /**
     * @param ColumnInterface[]                                        $columns
     * @param string[]                                                 $identifierFields
     * @param array<string, array{class-string, array<string, mixed>}> $expectedFields   Field name => [form type, options], in the order they must be added
     */
    #[DataProvider('provideColumnScenarios')]
    #[Test]
    public function build_form_adds_one_field_per_mapped_column(array $columns, array $identifierFields, array $expectedFields): void
    {
        $entity = new EditFormBuilderEntity();
        $form   = $this->createStub(FormInterface::class);

        $addCalls = [];

        $formBuilder = $this->createMock(FormBuilderInterface::class);
        $formBuilder->expects($this->exactly(\count($expectedFields)))
            ->method('add')
            ->willReturnCallback(function (string $name, string $type, array $options) use (&$addCalls, $formBuilder) {
                $addCalls[$name] = [$type, $options];

                return $formBuilder;
            });
        $formBuilder->expects($this->once())
            ->method('getForm')
            ->willReturn($form);

        $formFactory = $this->createMock(FormFactoryInterface::class);
        $formFactory->expects($this->once())
            ->method('createBuilder')
            ->with(FormType::class, $entity)
            ->willReturn($formBuilder);

        $builder = new EditFormBuilder($formFactory, new ColumnToFormTypeMapper());

        $this->assertSame($form, $builder->buildForm($entity, $columns, $identifierFields));
        $this->assertSame($expectedFields, $addCalls);
    }

    public static function provideColumnScenarios(): iterable
    {
        yield 'maps every supported column' => [
            [TextColumn::new('name', 'Name'), NumberColumn::new('price', 'Price')],
            [],
            [
                'name'  => [TextType::class, ['label' => 'Name']],
                'price' => [NumberType::class, ['label' => 'Price', 'html5' => true]],
            ],
        ];

        yield 'skips action columns' => [
            [TextColumn::new('name', 'Name'), ActionColumn::fromActions('actions', 'Actions', new Actions([]))],
            [],
            [
                'name' => [TextType::class, ['label' => 'Name']],
            ],
        ];

        yield 'disables identifier fields only' => [
            [NumberColumn::new('id', 'ID'), TextColumn::new('name', 'Name')],
            ['id'],
            [
                'id'   => [NumberType::class, ['label' => 'ID', 'html5' => true, 'disabled' => true]],
                'name' => [TextType::class, ['label' => 'Name']],
            ],
        ];
    }

    #[Test]
    public function a_money_column_stored_in_cents_is_edited_in_currency_units(): void
    {
        $entity        = new EditFormBuilderEntity();
        $entity->price = 2500;

        $form = (new EditFormBuilder(Forms::createFormFactory(), new ColumnToFormTypeMapper()))
            ->buildForm($entity, [MoneyColumn::new('price', 'Price')], []);

        $this->assertSame('25.00', $form->get('price')->getViewData());

        $form->submit(['price' => '30.5']);

        $this->assertTrue($form->isSynchronized());
        $this->assertSame(3050, $entity->price);
    }

    #[Test]
    public function it_builds_a_form_without_columns_the_entity_does_not_carry(): void
    {
        $entity = new EditFormBuilderEntity();
        $form   = (new EditFormBuilder(Forms::createFormFactory(), new ColumnToFormTypeMapper()))
            ->buildForm($entity, [
                TextColumn::new('name', 'Name'),
                TextColumn::new('fullName', 'Full Name'),
                NumberColumn::new('id', 'ID'),
            ], ['id']);

        $this->assertTrue($form->has('name'));
        $this->assertTrue($form->has('id'));
        $this->assertFalse($form->has('fullName'));
        $this->assertTrue($form->get('id')->isDisabled());
    }

    #[Test]
    public function a_boolean_column_without_render_as_switch_binds_as_a_checkbox(): void
    {
        $entity         = new EditFormBuilderEntity();
        $entity->active = true;

        $form = (new EditFormBuilder(Forms::createFormFactory(), new ColumnToFormTypeMapper()))
            ->buildForm($entity, [BooleanColumn::new('active', 'Active')], []);

        $this->assertInstanceOf(CheckboxType::class, $form->get('active')->getConfig()->getType()->getInnerType());
        $this->assertTrue($form->createView()['active']->vars['checked']);

        $form->submit([]);

        $this->assertTrue($form->isSynchronized());
        $this->assertFalse($entity->active);
    }

    #[Test]
    public function a_checked_boolean_checkbox_sets_the_flag(): void
    {
        $entity         = new EditFormBuilderEntity();
        $entity->active = false;

        $form = (new EditFormBuilder(Forms::createFormFactory(), new ColumnToFormTypeMapper()))
            ->buildForm($entity, [BooleanColumn::new('active', 'Active')], []);

        $form->submit(['active' => '1']);

        $this->assertTrue($form->isSynchronized());
        $this->assertTrue($entity->active);
    }
}

final class EditFormBuilderEntity
{
    public int $id = 1;

    public string $name = 'Ada';

    public int $price = 10;

    public bool $active = false;
}
