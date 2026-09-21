<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

final readonly class AjaxBulkQueryDto
{
    /**
     * @param list<mixed>          $ids           rows the user checked
     * @param bool                 $allMatching   act on every row matching the displayed request
     * @param list<mixed>          $deselectedIds rows unchecked after $allMatching was set
     * @param array<string, mixed> $query         the DataTables request the table was displaying
     */
    public function __construct(
        public string $dataTable,
        public string $action,
        public array $ids = [],
        public bool $allMatching = false,
        public array $deselectedIds = [],
        public array $query = [],
    ) {
    }
}
