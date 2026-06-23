<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * Trimmed, non-empty global search term applied across globally searchable columns.
 *
 * The regex flag is carried as metadata only; nothing interprets it yet.
 */
final readonly class GlobalSearchIntent
{
    public function __construct(
        public string $value,
        public bool $regexRequested = false,
    ) {
    }
}
