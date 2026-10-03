<?php

declare(strict_types=1);

namespace App\DataTable;

use App\Entity\Customer;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Columns and filters come from the #[DataTableColumn] and #[DataTableFilter] attributes on Customer.
 */
#[AsDataTable(Customer::class)]
final class CustomersDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide();
    }
}
