<?php

namespace Pentiminax\UX\DataTables\Query;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

/**
 * Immutable context object containing all data needed for filter execution.
 *
 * This DTO provides a clean interface for passing request data, column definitions,
 * and query configuration to filters and strategies.
 */
final readonly class QueryFilterContext
{
    public function __construct(
        public DataTableRequest $request,
        public array $columns,
        public string $alias = 'e',
    ) {
    }
}
