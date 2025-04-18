<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Enum\Language;
use Pentiminax\UX\DataTables\Model\DataTable;
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
                ->ordering(true)
                ->paging(true)
                ->processing(true)
                ->scrollX(true)
                ->scrollY('200px')
                ->search('search')
                ->searching(true)
                ->serverSide(true)
                ->stateSave(true)
                ->pageLength(10)
                ->language(Language::FR)
                ->layout(new LayoutOption())
                ->lengthMenu([10, 25, 50])
                ->responsive()
                ->extensions([$selectExtension]);

        $this->assertEquals('tableId', $table->getId());

        $expectedExtensions = [
            'select' => $selectExtension->jsonSerialize()
        ];

        $this->assertEquals($expectedExtensions, $table->getExtensions());
    }
}