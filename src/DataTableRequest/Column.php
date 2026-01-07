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
        public ?ColumnControl $columnControl = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            data: $data['data'],
            name: $data['name'],
            searchable: $data['searchable'],
            orderable: $data['orderable'],
            search: isset($data['search']) ? Search::fromArray($data['search']) : null,
            columnControl: isset($data['columnControl']) ? ColumnControl::fromArray($data['columnControl']) : null,
        );
    }
}
