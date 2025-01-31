<?php



namespace Pentiminax\UX\DataTables\Builder;

use Pentiminax\UX\DataTables\Model\DataTable;

class DataTableBuilder implements DataTableBuilderInterface
{
    public function createDataTable(?string $id = null): DataTable
    {
        return new DataTable($id);
    }
}
