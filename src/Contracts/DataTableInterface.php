<?php

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\DataTableQuery;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;

interface DataTableInterface
{
    public function configureDataTable(DataTable $table): DataTable;

    public function configureColumns(): iterable;

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions;

    public function configureButtonsExtension(ButtonsExtension $extension): ButtonsExtension;

    public function configureColumnControlExtension(ColumnControlExtension $extension): ColumnControlExtension;

    public function configureSelectExtension(SelectExtension $extension): SelectExtension;

    public function fetchData(DataTableQuery $query): DataTableResult;

    public function getDataTable(): DataTable;
}
