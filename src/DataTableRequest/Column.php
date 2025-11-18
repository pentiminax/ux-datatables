<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class Column
{
    public function __construct(
        public string $data,
        public string $name,
        public bool $searchable,
        public bool $orderable,
        public ?Search $search = null,
    ) {
    }
}
