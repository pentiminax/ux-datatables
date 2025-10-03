<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\DataTableResult;

interface DataProviderInterface
{
    public function fetch(): DataTableResult;
}