<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'])]
class AutoDetectWithGroupsDataTable extends AbstractDataTable
{
    public function __construct(?ColumnAutoDetectorInterface $columnAutoDetector = null)
    {
        parent::__construct(
            columnResolver: new ColumnResolver(columnAutoDetector: $columnAutoDetector),
        );
    }
}
