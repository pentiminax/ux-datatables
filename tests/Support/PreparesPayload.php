<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Runtime\DataSourceMode;
use Pentiminax\UX\DataTables\Runtime\PreparedDataTable;
use Pentiminax\UX\DataTables\Runtime\RenderingPreparer;

trait PreparesPayload
{
    /**
     * @return array<string, mixed>
     */
    private static function payloadOf(DataTable $table, RenderingPreparer $preparer = new RenderingPreparer()): array
    {
        return $preparer->prepare($table, $preparer->resolveOptIns($table, null))->getView();
    }

    /**
     * A payload built from the table as it is, without a preparer, for the profiler.
     *
     * @param list<ColumnInterface>|null $columns           the permitted columns, all of them by default
     * @param list<ColumnInterface>|null $configuredColumns the table's columns by default
     */
    private static function preparedFor(DataTable $table, DataSourceMode $mode = DataSourceMode::Inline, ?array $columns = null, ?array $configuredColumns = null): PreparedDataTable
    {
        $configuredColumns ??= array_values($table->getColumns());

        return new PreparedDataTable(
            id: $table->getId(),
            attributes: $table->getAttributes(),
            dataTableClass: $table->getDataTableClass(),
            dataSourceMode: $mode,
            options: $table->getOptions(),
            columns: $columns ?? $configuredColumns,
            configuredColumns: $configuredColumns,
            mercure: $table->getMercureConfig(),
            filters: $table->getFilters()?->jsonSerialize(),
            editModalTemplate: $table->getEditModalTemplate(),
            editModalAdapter: $table->getEditModalAdapter(),
        );
    }
}
