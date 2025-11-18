<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

interface DataProviderInterface
{
    public function fetchData(DataTableRequest $query): DataTableResult;
}
