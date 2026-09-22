<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Deliberately absent from TestHarnessAppKernel's service definitions.
 */
final class HarnessUnregisteredDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
