<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Contracts\DataTableInterface;

abstract class AbstractDataTable implements DataTableInterface
{
    private DataTable $table;

    public function __construct()
    {
        $this->table = $this->configureDataTable(
            new DataTable($this->getClassName())
        );

        $this->table->columns(
            iterator_to_array($this->configureColumns())
        );

        $this->table->setExtensions(
            $this->configureExtensions(new DataTableExtensions())
        );
    }

    public function getDataTable(): DataTable
    {
        return $this->table;
    }

    public function configureColumns(): iterable
    {
        return $this->getDataTable()->getColumns();
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table;
    }

    public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
    {
        return $extensions;
    }

    private function getClassName(): string
    {
        return (new \ReflectionClass($this))->getShortName();
    }
}