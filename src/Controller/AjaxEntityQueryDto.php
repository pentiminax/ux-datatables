<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Controller;

final readonly class AjaxEntityQueryDto
{
    public function __construct(
        public string $dataTable,
        public int|string $id,
    ) {
    }
}
