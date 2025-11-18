<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class Order
{
    public function __construct(
        public int $column,
        public string $dir,
        public string $name,
    ) {
    }
}
