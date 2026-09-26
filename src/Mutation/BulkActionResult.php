<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

final readonly class BulkActionResult
{
    public function __construct(
        public int $processed,
        public int $skipped,
    ) {
    }
}
