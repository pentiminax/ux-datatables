<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;

#[AsDataTable(entityClass: \stdClass::class)]
class AutoDetectWithoutApiPlatformOptInDataTable extends AbstractDataTable
{
    public function __construct(private readonly ?ColumnAutoDetector $columnAutoDetector = null)
    {
        parent::__construct();
        $this->setDataTableInfrastructure(DataTableInfrastructure::createDefault(
            columnResolver: new ColumnResolver(columnAutoDetector: $this->columnAutoDetector)
        ));
    }
}
