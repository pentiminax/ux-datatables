<?php

namespace Pentiminax\UX\DataTables\Dto;

final readonly class AjaxEditRequestDto
{
    public function __construct(
        public string $entity,
        public string $field,
        public int $id,
        public bool $newValue,
    ) {
    }
}
