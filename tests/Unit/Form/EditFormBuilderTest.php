<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Form;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * @internal
 */
class EditFormBuilderTest extends TestCase
{
    /**
     * @param ColumnInterface[]                                        $columns
     * @param string[]                                                 $identifierFields
     * @param array<string, array{class-string, array<string, mixed>}> $expectedFields   Field name => [form type, options], in the order they must be added
     */
    #[DataProvider('provideColumnScenarios')]
    public function test_build_form_adds_one_field_per_mapped_column(array $columns, array $identifierFields, array $expectedFields): void
    {
        $entity = new \stdClass();
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
}
