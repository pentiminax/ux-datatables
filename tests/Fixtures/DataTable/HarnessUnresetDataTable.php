<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

final class HarnessUnresetDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([['id' => 1, 'title' => 'Configured']]);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('title');
    }
}
