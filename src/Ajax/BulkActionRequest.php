<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

/**
 * @internal
 */
final readonly class BulkActionRequest
{
    /**
     * @param array<string, mixed> $query the DataTables request the table was displaying
     */
    public function __construct(
        public string $dataTable,
        public string $action,
        public array $query = [],
    ) {
    }
}
