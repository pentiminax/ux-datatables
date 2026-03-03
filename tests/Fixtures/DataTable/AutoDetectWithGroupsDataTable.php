<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'])]
class AutoDetectWithGroupsDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return $this->autoDetectColumns();
    }

    /**
     * @param string[] $groups
     *
     * @return AbstractColumn[]
     */
    public function callAutoDetectColumns(array $groups = []): array
    {
        return $this->autoDetectColumns($groups);
    }
}
