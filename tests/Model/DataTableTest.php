<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Options\AjaxOption;
use Pentiminax\UX\DataTables\Model\Options\LayoutOption;
use PHPUnit\Framework\TestCase;

class DataTableTest extends TestCase
{
    public function testDataTable(): void
    {
        $selectExtension = new SelectExtension();

        $table =
            (new DataTable('tableId'))
                ->autoWidth(true)
                ->ajax(new AjaxOption('/url'))
                ->caption('Table caption')
                ->deferRender(true)
                ->displayStart(10)
                ->info(true)
                ->lengthChange(true)
                ->ordering()
                ->withoutPaging(true)
                ->processing(true)
                ->scrollX(true)
                ->scrollY('200px')
                ->search('search')
                ->searching(true)
                ->serverSide(true)
                ->stateSave(true)
                ->pageLength(10)
                ->language(Language::FR)
                ->layout()
                ->lengthMenu([10, 25, 50])
                ->responsive()
                ->columnControl()
                ->extensions([$selectExtension]);

        $this->assertEquals('tableId', $table->getId());

        $expectedExtensions = [
            'columnControl' => (new ColumnControlExtension())->jsonSerialize(),
            'select' => $selectExtension->jsonSerialize(),
            'responsive' => true
        ];

        $this->assertEquals($expectedExtensions, $table->getExtensions());
    }

    public function testPagingOption(): void
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
            'buttons' => 5,
            'firstLast' => false,
            'numbers' => false,
            'previousNext' => false,
        ];

        $this->assertSame($expectedPaging, $table->getOption('paging'));
    }
}