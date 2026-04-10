<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableExtensionsTest extends TestCase
{
    #[Test]
    public function it_configures_all_extensions_through_configure_extensions(): void
    {
        $table = new class extends AbstractDataTable {
            public function configureDataTable(DataTable $table): DataTable
            {
                return $table->layout([
                    'topStart'    => Feature::BUTTONS,
                    'topEnd'      => Feature::SEARCH,
                    'bottomStart' => Feature::INFO,
                    'bottomEnd'   => Feature::PAGING,
                ]);
            }

            public function configureColumns(): iterable
            {
                yield TextColumn::new('id');
            }

            public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
            {
                return $extensions
                    ->addExtension(new ButtonsExtension([ButtonType::CSV]))
                    ->addExtension(new ColumnControlExtension())
                    ->addExtension(new SelectExtension());
            }
        };

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

    #[Test]
    public function it_injects_buttons_when_in_array_position(): void
    {
        $table = new class extends AbstractDataTable {
            public function configureDataTable(DataTable $table): DataTable
            {
                return $table->layout([
                    'topEnd' => [Feature::SEARCH, Feature::BUTTONS],
                ]);
            }

            public function configureColumns(): iterable
            {
                yield TextColumn::new('id');
            }

            public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
            {
                return $extensions
                    ->addExtension(new ButtonsExtension([ButtonType::CSV]));
            }
        };

        $layout = $table->getDataTable()->getOptions()['layout'];

        $this->assertSame('search', $layout['topEnd'][0]);
        $this->assertSame([
            'buttons' => [
                [
                    'extend'        => 'csv',
                    'exportOptions' => [
                        'columns' => ':visible:not(.not-exportable)',
                    ],
                ],
            ],
        ], $layout['topEnd'][1]);
    }

    #[Test]
    public function it_configures_select_extension_with_checkbox_via_closure(): void
    {
        $table = new class extends AbstractDataTable {
            public function configureDataTable(DataTable $table): DataTable
            {
                return $table;
            }

            public function configureColumns(): iterable
            {
                yield TextColumn::new('id');
            }

            public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
            {
                return $extensions->addSelectExtension(
                    fn (SelectExtension $select) => $select->withCheckbox()->headerCheckbox()
                );
            }
        };

        $extensions = $table->getDataTable()->getExtensions();

        $this->assertTrue($extensions['select']['withCheckbox']);
        $this->assertTrue($extensions['select']['headerCheckbox']);
    }
}
