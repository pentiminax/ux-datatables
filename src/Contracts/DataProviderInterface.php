<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;

interface DataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult;
}
