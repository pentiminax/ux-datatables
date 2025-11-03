<?php

namespace Pentiminax\UX\DataTables\Builder;

use Pentiminax\UX\DataTables\Model\DataTable;

interface DataTableBuilderInterface
{
    public function createDataTable(?string $id = null): DataTable;
}
