<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class ColumnControl
{
    public function __construct(
        public ?ColumnControlSearch $search = null,
        public array $list = []
    ) {
    }

    public static function fromArray(array $data): ColumnControl
    {
        return new self(
            search: isset($data['search']) ? ColumnControlSearch::fromArray($data['search']) : null,
            list: $data['list'] ?? []
        );
    }
}