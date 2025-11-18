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

    public static function fromArray(array $order): self
    {
        return new self(
            column: $order['column'] ?? 0,
            dir: $order['dir'] ?? 'asc',
            name: $order['name'] ?? ''
        );
    }
}