<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataTableRequest;

use Symfony\Component\HttpFoundation\Request;

final readonly class Columns
{
    public function __construct(
        /** @var Column[] */
        private array $columns,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        $columns = [];
        foreach (RequestInputBag::resolve($request)->all('columns') as $column) {
            if (!\is_array($column)) {
                continue;
            }

            $parsed                 = Column::fromArray($column);
            $columns[$parsed->name] = $parsed;
        }

        return new self($columns);
    }

    /**
     * @return array<string, Column> request columns keyed by column name
     */
    public function all(): array
    {
        return $this->columns;
    }

    public function getColumnByName(string $name): ?Column
    {
        return $this->columns[$name] ?? null;
    }

    public function getColumnByIndex(int $index): ?Column
    {
        $indexed = array_values($this->columns);

        return $indexed[$index] ?? null;
    }
}
