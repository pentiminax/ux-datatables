<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * Applies application-wide defaults to every table, after the `data_tables` configuration and
 * before the table's own configureDataTable(), which has the last word.
 *
 * Implementations are autoconfigured and ordered by service tag priority. They only receive the
 * DataTable; Ajax routes, tokens and table-purpose validation are out of their reach.
 */
interface DataTableDefaultsConfiguratorInterface
{
    public function configure(DataTable $table): void;
}
