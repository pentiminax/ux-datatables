<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class ColumnControl
{
    public function __construct(
        public ColumnControlSearch $search
    ) {
    }

    public static function fromArray(array $data): ColumnControl
    {
        return new self(
            search: ColumnControlSearch::fromArray($data['search'])
        );
    }
}