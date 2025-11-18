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

    public static function fromArray(array $column): self
    {
        return new self(
            data: $column['data'] ?? '',
            name: $column['name'] ?? '',
            searchable: filter_var($column['searchable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            orderable: filter_var($column['orderable'] ?? false, FILTER_VALIDATE_BOOLEAN),
            search: Search::fromArray($column['search'] ?? [])
        );
    }
}