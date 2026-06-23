<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * A standard DataTables per-column search (Column.search), trimmed non-empty.
 *
 * The regex flag is carried as metadata only; nothing interprets it yet.
 */
final readonly class ColumnSearchIntent
{
    public function __construct(
        public ColumnReadReference $column,
        public string $value,
        public bool $regexRequested = false,
    ) {
    }
}
