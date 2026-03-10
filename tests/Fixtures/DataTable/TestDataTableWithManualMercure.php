<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(entityClass: \stdClass::class, mercure: true)]
class TestDataTableWithManualMercure extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->mercure(
            hubUrl: '/.well-known/mercure',
            topics: ['manual/topic'],
        );
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
