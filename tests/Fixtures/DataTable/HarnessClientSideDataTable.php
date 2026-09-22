<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

final class HarnessClientSideDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->pageLength(25)
            ->data([
                ['id' => 1, 'title' => 'Symfony 7'],
                ['id' => 2, 'title' => 'UX in Action'],
                ['id' => 3, 'title' => 'Escaping &quot;quoted&quot; & <tagged> titles'],
            ]);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
        yield TextColumn::new('title');
    }
}
