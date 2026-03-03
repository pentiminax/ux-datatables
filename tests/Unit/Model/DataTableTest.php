<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Options\SearchOption;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DataTable::class)]
final class DataTableTest extends TestCase
{
    #[Test]
    public function it_configures_datatable_options(): void
    {
        $selectExtension = new SelectExtension();

        $table = (new DataTable('tableId'))
                ->autoWidth(true)
                ->ajax(url: '/url')
                ->caption('Table caption')
                ->deferRender(true)
                ->displayStart(10)
                ->info(true)
                ->lengthChange(true)
                ->ordering()
                ->withoutPaging()
                ->processing()
                ->scrollX(true)
                ->scrollY('200px')
                ->search('search')
                ->searching()
                ->serverSide()
                ->apiPlatform()
                ->stateSave()
                ->pageLength(10)
                ->language(Language::FR)
                ->lengthMenu([10, 25, 50])
                ->responsive()
                ->columnControl()
                ->withSearchOption(SearchOption::new())
                ->extensions([$selectExtension]);

        $this->assertEquals('tableId', $table->getId());

        $expectedExtensions = [
            'columnControl' => (new ColumnControlExtension())->jsonSerialize(),
            'select'        => $selectExtension->jsonSerialize(),
            'responsive'    => true,
        ];

        $this->assertEquals($expectedExtensions, $table->getExtensions());
        $this->assertTrue($table->getOption('apiPlatform'));
    }

    #[Test]
    public function it_configures_layout_option(): void
    {
        $table = new DataTable('testTable');

        $table->layout(
            topStart: Feature::BUTTONS,
            topEnd: Feature::PAGE_LENGTH,
            bottomStart: Feature::PAGING,
            bottomEnd: Feature::INFO
        );

        $expectedLayout = [
            'topStart'    => 'buttons',
            'topEnd'      => 'pageLength',
            'bottomStart' => [
                'paging' => true,
            ],
            'bottomEnd' => 'info',
        ];

        $this->assertSame($expectedLayout, $table->getOption('layout')->jsonSerialize());
    }

    #[Test]
    public function it_configures_paging_option(): void
    {
        $table = new DataTable('testTable');

        $table->paging(
            boundaryNumbers: false,
            buttons: 5,
            firstLast: false,
            numbers: false,
            previousNext: false
        );

        $expectedPaging = [
            'boundaryNumbers' => false,
            'buttons'         => 5,
            'firstLast'       => false,
            'numbers'         => false,
            'previousNext'    => false,
        ];

        $this->assertSame($expectedPaging, $table->getOption('paging'));
    }
}
