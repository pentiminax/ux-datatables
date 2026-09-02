<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

/**
 * Optional provider capability for server-side export: stream every filtered row without
 * pagination or a COUNT query.
 *
 * Implementations must apply the request's search, ordering, and configured filters exactly as
 * fetchData() does, then yield rows in batches rather than materializing the whole result set --
 * an export exists precisely to avoid holding a full table in memory. ExportService prefers
 * iterateRows() and falls back to fetchData()->data when a provider does not implement this.
 */
interface StreamingDataProviderInterface extends DataProviderInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function iterateRows(DataTableRequest $request): iterable;
}
