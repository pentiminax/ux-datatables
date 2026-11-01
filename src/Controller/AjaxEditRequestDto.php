<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

final readonly class AjaxEditRequestDto
{
    /**
     * @param array<string, mixed> $query the forwarded query parameters the table was rendered with
     */
    public function __construct(
        public string $dataTable,
        public int|string $id,
        public string $field,
        public bool $newValue,
        public array $query = [],
    ) {
    }
}
