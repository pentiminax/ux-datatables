<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

#[AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'], apiPlatform: true)]
class TestDataTableWithSerializationGroups extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->ajax('/api/products');
    }
}
