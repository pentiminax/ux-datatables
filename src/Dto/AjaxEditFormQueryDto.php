<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Dto;

final readonly class AjaxEditFormQueryDto
{
    public function __construct(
        public string $entity,
        public int|string $id,
        public ?string $dataTableClass = null,
    ) {
    }
}
