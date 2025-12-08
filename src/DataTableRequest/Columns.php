<?php

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\Request;

final readonly class Columns
{
    public function __construct(
        /** @var Column[] */
        private array $columns
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $columns = [];
        foreach ($request->query->all('columns') as $column) {
            $columns[$column['name']] = Column::fromArray($column);
        }

        return new self($columns);
    }

    public function getColumnByName(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }
}