<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * Normalized pagination window.
 *
 * A null limit means "no max-result limit", preserving the current behaviour for
 * length values <= 0.
 */
final readonly class PaginationIntent
{
    public function __construct(
        public int $offset,
        public ?int $limit,
    ) {
    }
}
