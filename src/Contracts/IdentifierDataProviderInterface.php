<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

/**
 * A provider that can name the rows of a request and tell which identifiers belong to the table's
 * permanent scope.
 *
 * A provider implementing this interface is trusted to cover both features. When it is also feature
 * aware, it must answer {@see \Pentiminax\UX\DataTables\Enum\DataSourceFeature::RowScope} and
 * {@see \Pentiminax\UX\DataTables\Enum\DataSourceFeature::IdentifierCollection} through
 * {@see FeatureAwareDataProviderInterface::supports()}, and the bundle uses only what it declares.
 */
interface IdentifierDataProviderInterface
{
    /**
     * Names every row matching the request without loading the rows themselves, to resolve a
     * "select every matching row" bulk selection.
     *
     * @param string|null $field the field holding the identifiers the client selection speaks in,
     *                           or null to answer with the entity's own identifier; a field the
     *                           entity does not declare falls back to that identifier too
     *
     * @return list<int|string>
     */
    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array;

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
