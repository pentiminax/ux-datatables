<?php

declare(strict_types=1);

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
        $columnIndex = \is_scalar($data['column'] ?? null) ? (int) $data['column'] : 0;
        $dir         = \is_string($data['dir'] ?? null) ? strtolower(trim($data['dir'])) : '';
        $dir         = \in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';

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
