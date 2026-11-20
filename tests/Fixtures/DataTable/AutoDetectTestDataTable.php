<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\DataTable;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(entityClass: \stdClass::class, apiPlatform: true)]
class AutoDetectTestDataTable extends AbstractDataTable
{
}
