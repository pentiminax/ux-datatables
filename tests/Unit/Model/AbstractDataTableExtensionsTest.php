<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\Extensions\Button;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Tests\Support\ConfigurableDataTable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
#[CoversClass(Button::class)]
final class AbstractDataTableExtensionsTest extends TestCase
{
    #[Test]
    public function it_configures_all_extensions_through_configure_extensions(): void
    {
        $table = $this->tableWith(
            [
                'topStart'    => Feature::BUTTONS,
                'topEnd'      => Feature::SEARCH,
                'bottomStart' => Feature::INFO,
                'bottomEnd'   => Feature::PAGING,
            ],
            fn (DataTableExtensions $extensions) => $extensions
                ->addExtension(new ButtonsExtension([ButtonType::CSV]))
                ->addExtension(new ColumnControlExtension())
                ->addExtension(new SelectExtension()),
        );

        $this->assertSame([
            'topStart' => [
                'buttons' => [
                    [
                        'extend'        => 'csv',
                        'exportOptions' => [
                            'columns' => ':visible:not(.not-exportable)',
                        ],
                    ],
                ],
            ],
            'topEnd'      => 'search',
            'bottomStart' => 'info',
            'bottomEnd'   => 'paging',
        ], $table->getDataTable()->getOptions()['layout']);

        $this->assertSame([
            'columnControl' => [
                [
                    'target'  => 0,
                    'content' => [
                        'order',
                        [
                            'orderAsc',
                            'orderDesc',
                            'spacer',
                            'orderAddAsc',
                            'orderAddDesc',
                            'spacer',
                            'orderRemove',
                        ],
                    ],
                ],
                [
                    'target'  => 1,
                    'content' => ['search'],
                ],
            ],
            'select' => [
                'blurable'       => false,
                'className'      => 'selected',
                'info'           => true,
                'items'          => 'row',
                'keys'           => false,
                'style'          => 'single',
                'toggleable'     => true,
                'headerCheckbox' => false,
                'withCheckbox'   => false,
            ],
        ], $table->getDataTable()->getExtensions());
    }

    /**
     * @param array<string, mixed>    $layout
     * @param list<Button|ButtonType> $buttons
     * @param array<string, mixed>    $expectedLayout
     */
    #[Test]
    #[DataProvider('buttonsLayoutProvider')]
    public function it_injects_buttons_into_the_layout(array $layout, array $buttons, array $expectedLayout): void
    {
        $table = $this->tableWith(
            $layout,
            fn (DataTableExtensions $extensions) => $extensions->addExtension(new ButtonsExtension($buttons)),
        );

        $this->assertSame($expectedLayout, $table->getDataTable()->getOptions()['layout']);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<Button|ButtonType>, array<string, mixed>}>
     */
    public static function buttonsLayoutProvider(): iterable
    {
        yield 'customized buttons in a single feature position' => [
            [
                'topStart' => Feature::BUTTONS,
                'topEnd'   => Feature::SEARCH,
            ],
            [
                Button::csv()
                    ->text('Export CSV')
                    ->className('btn btn-primary')
                    ->exportOptions(['columns' => ':visible']),
            ],
            [
                'topStart' => [
                    'buttons' => [
                        [
                            'extend'        => 'csv',
                            'text'          => 'Export CSV',
                            'className'     => 'btn btn-primary',
                            'exportOptions' => [
                                'columns' => ':visible',
                            ],
                        ],
                    ],
                ],
                'topEnd' => 'search',
            ],
        ];

        yield 'buttons nested in an array position' => [
            [
                'topEnd' => [Feature::SEARCH, Feature::BUTTONS],
            ],
            [ButtonType::CSV],
            [
                'topEnd' => [
                    'search',
                    [
                        'buttons' => [
                            [
                                'extend'        => 'csv',
                                'exportOptions' => [
                                    'columns' => ':visible:not(.not-exportable)',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    #[Test]
    public function it_configures_select_extension_with_checkbox_via_closure(): void
    {
        $table = $this->tableWith(
            [],
            fn (DataTableExtensions $extensions) => $extensions->addSelectExtension(
                fn (SelectExtension $select) => $select->withCheckbox()->headerCheckbox()
            ),
        );

        $extensions = $table->getDataTable()->getExtensions();

        $this->assertTrue($extensions['select']['withCheckbox']);
        $this->assertTrue($extensions['select']['headerCheckbox']);
    }

    /**
     * @param array<string, mixed>                               $layout              an empty array leaves the default layout untouched
     * @param \Closure(DataTableExtensions): DataTableExtensions $configureExtensions
     */
    private function tableWith(array $layout, \Closure $configureExtensions): AbstractDataTable
    {
        return new ConfigurableDataTable(
            [TextColumn::new('id')],
            extensions: $configureExtensions,
            configureTable: static fn (DataTable $table): DataTable => [] === $layout ? $table : $table->layout($layout),
        );
    }
}
