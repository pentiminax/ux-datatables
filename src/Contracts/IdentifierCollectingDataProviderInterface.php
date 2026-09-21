<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

/**
 * A provider that can name every row matching a request without loading the rows themselves.
 *
 * Used by the bulk endpoint to resolve a "select every matching row" selection: the browser sends
 * the request it was displaying, and the provider answers with the identifiers it covers.
 */
interface IdentifierCollectingDataProviderInterface
{
    /**
     * @param string|null $field the field holding the identifiers the client selection speaks in,
     *                           or null to answer with the entity's own identifier; a field the
     *                           entity does not declare falls back to that identifier too
     *
     * @return list<int|string>
     */
    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array;
}
