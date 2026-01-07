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

    public static function fromArray(array $data, Columns $columns): self
    {
        $columnIndex = (int) ($data['column'] ?? 0);
        $dir         = $data['dir'] ?? 'asc';

        $column = $columns->getColumnByIndex($columnIndex);

        if (null === $column) {
            $name = "column_$columnIndex";
        } else {
            $name = $column->name;
        }

        return new self(
            column: $columnIndex,
            dir: $dir,
            name: $name,
        );
    }
}
