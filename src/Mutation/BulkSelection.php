<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

/**
 * What the browser says the user selected.
 *
 * Either an explicit list of row identifiers, or "every row matching the current filters", in
 * which case the identifiers are resolved server-side from $query — the DataTables request the
 * table was displaying — and $deselectedIds is subtracted from the result.
 */
final readonly class BulkSelection
{
    /**
     * @param list<int|string>     $ids
     * @param list<int|string>     $deselectedIds
     * @param array<string, mixed> $query
     */
    public function __construct(
        public array $ids = [],
        public bool $allMatching = false,
        public array $deselectedIds = [],
        public array $query = [],
    ) {
    }
}
