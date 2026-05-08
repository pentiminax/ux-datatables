<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;

#[AsDataTable(entityClass: \stdClass::class, serializationGroups: ['product:list'], apiPlatform: true)]
class AutoDetectWithGroupsDataTable extends AbstractDataTable
{
    public function __construct(private readonly ?ColumnAutoDetectorInterface $columnAutoDetector = null)
    {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            columnResolver: new ColumnResolver(columnAutoDetector: $this->columnAutoDetector)
        ));
    }
}
