<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

final readonly class AjaxEditFormRequestDto
{
    /**
     * @param array<string, mixed> $formData
     */
    public function __construct(
        public string $dataTable,
        public int|string $id,
        public array $formData = [],
    ) {
    }
}
