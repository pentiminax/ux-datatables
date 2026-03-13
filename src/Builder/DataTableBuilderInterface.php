<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Builder;

use Pentiminax\UX\DataTables\Model\DataTable;

interface DataTableBuilderInterface
{
    public function createDataTable(string $id): DataTable;
}
