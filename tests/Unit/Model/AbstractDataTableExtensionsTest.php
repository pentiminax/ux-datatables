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
                return $table->layout(Feature::BUTTONS);
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
            'topEnd' => 'search',
            'bottomStart' => 'info',
            'bottomEnd' => [
                'paging' => true,
            ],
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
}
