<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

/**
 * A provider that can tell which identifiers belong to the table's permanent scope.
 *
 * Used by the bulk endpoint to refuse identifiers the browser sent for rows the table could never
 * have displayed, such as another tenant's rows in a table scoped through customizeQueryBuilder().
 */
interface ScopedIdentifierProviderInterface
{
    /**
     * Returns the subset of $ids that the table's permanent scope
     * (customizeQueryBuilder() only, without interactive search or filters) contains.
     *
     * @param list<int|string> $ids
     * @param string           $field the field the identifiers speak in
     *
     * @return list<int|string> the in-scope ids, as they were given and in the same order
     */
    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array;
}
