<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;

interface DataTableInterface
{
    public function configureColumns(): iterable;

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions;

    public function getDataTable(): DataTable;
}