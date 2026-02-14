<?php

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

class TestDataTableWithoutAttribute extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
