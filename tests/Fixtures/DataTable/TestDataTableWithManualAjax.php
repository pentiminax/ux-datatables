<?php

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(entityClass: \stdClass::class)]
class TestDataTableWithManualAjax extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/custom-endpoint');
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
