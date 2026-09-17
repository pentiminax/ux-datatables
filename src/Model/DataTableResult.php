<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

final readonly class DataTableResult
{
    public function __construct(
        public int $recordsTotal,
        public int $recordsFiltered,
        public iterable $data,
    ) {
    }
}
