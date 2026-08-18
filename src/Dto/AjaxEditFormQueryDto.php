<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Dto;

final readonly class AjaxEditFormQueryDto
{
    public function __construct(
        public string $dataTable,
        public int|string $id,
    ) {
    }
}
