<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\DataTableQuery;
use Pentiminax\UX\DataTables\Model\DataTableResult;

interface DataProviderInterface
{
    public function fetchData(DataTableQuery $query): DataTableResult;
}