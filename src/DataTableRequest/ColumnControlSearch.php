<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

final readonly class ColumnControlSearch
{
    public function __construct(
        public string $value,
        public string $logic,
        public string $type,
    ) {
    }

    public static function fromArray(array $data): ColumnControlSearch
    {
        return new self(
            value: $data['value'],
            logic: $data['logic'],
            type: $data['type']
        );
    }
}
