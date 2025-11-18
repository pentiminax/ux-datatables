<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class DataTableRequest
{
    public function __construct(
        public ?int $draw,
        public int $start = 0,
        public int $length = 10,
        public ?Search $search = null,

        /** @var Column[] */
        public array $columns = [],

        /** @var Order[] */
        public array $order = [],
    ) {
    }
}
