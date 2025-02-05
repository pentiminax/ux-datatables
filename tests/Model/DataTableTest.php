<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableFeaturesOptions;
use PHPUnit\Framework\TestCase;

class DataTableTest extends TestCase
{
    public function testDataTable(): void
    {
        $featuresOptions = (new DataTableFeaturesOptions())
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
        ;

        $table = new DataTable('tableId');

        $table->setFeaturesOptions($featuresOptions);

        $this->assertEquals('tableId', $table->getId());
    }
}