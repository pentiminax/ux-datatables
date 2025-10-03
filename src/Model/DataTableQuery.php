<?php

namespace Pentiminax\UX\DataTables\Model;

final readonly class DataTableQuery
{
    public function __construct(
        public int $start = 0,
        public int $length = 10,
        public ?string $globalSearch = null,
    ) {}
}