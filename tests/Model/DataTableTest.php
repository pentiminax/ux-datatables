<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use PHPUnit\Framework\TestCase;

class DataTableTest extends TestCase
{
    public function testDataTable(): void
    {
        $selectExtension = new SelectExtension();

        $table =
            (new DataTable('tableId'))
                ->autoWidth(true)
                ->caption('Table caption')
                ->deferRender(true)
                ->info(true)
                ->lengthChange(true)
                ->ordering(true)
                ->paging(true)
                ->processing(true)
                ->scrollX(true)
                ->scrollY('200px')
                ->searching(true)
                ->serverSide(true)
                ->stateSave(true)
                ->pageLength(10)
                ->lengthMenu([10, 25, 50])
                ->extensions([$selectExtension]);

        $this->assertEquals('tableId', $table->getId());

        $expectedExtensions = [
            'select' => $selectExtension->toArray()
        ];

        $this->assertEquals($expectedExtensions, $table->getExtensions());
    }
}