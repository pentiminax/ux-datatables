<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
class AutoDetectTestDataTable extends AbstractDataTable
{
    public function __construct(private readonly ?ColumnAutoDetectorInterface $columnAutoDetector = null)
    {
        parent::__construct();
    }

    protected function createColumnResolver(): ColumnResolver
    {
        return new ColumnResolver(columnAutoDetector: $this->columnAutoDetector);
    }
}
