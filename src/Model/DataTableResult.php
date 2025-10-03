<?php

namespace Pentiminax\UX\DataTables\Model;

final readonly class DataTableResult
{
    public function __construct(
        public int $recordsTotal,
        public int $recordsFiltered,
        public iterable $rows
    ) {}
}