<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Dto;

final readonly class AjaxEditFormRequestDto
{
    public function __construct(
        public string $entity,
        public int|string $id,
        public array $formData = [],
        public array $topics = [],
        public ?string $dataTableClass = null,
    ) {
    }
}
