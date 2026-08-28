<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

/**
 * Optional provider capability for server-side CSV export: stream mapped rows
 * without pagination or COUNT queries.
 */
interface StreamingDataProviderInterface extends DataProviderInterface
{
    /**
     * @return iterable<array<string, mixed>>
     */
    public function iterateRows(DataTableRequest $request): iterable;
}
