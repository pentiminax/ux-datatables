<?php

declare(strict_types=1);

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
            searchable: 'true' === $data['searchable'],
            orderable: 'true'  === $data['orderable'],
            search: isset($data['search']) ? Search::fromArray($data['search']) : null,
            columnControl: isset($data['columnControl']) ? ColumnControl::fromArray($data['columnControl']) : null,
        );
    }

    /**
     * Search values carried on this column's search box, decoded.
     *
     * DataTables only ever transports one string per column: a multi-value facet
     * (checkbox group, multi-select) encodes its selection as a JSON array in that
     * string. Returns the decoded, trimmed, non-empty values either way — a plain
     * string search yields a single-element list, a JSON array yields one element
     * per entry, and no active search yields an empty list.
     *
     * Empty entries are dropped. A facet that depends on positional emptiness
     * (e.g. a `['', '2026-01-01']` date-range pair where the first slot means
     * "no lower bound") must not use this method — decode `search->value` directly.
     *
     * @return list<string>
     */
    public function searchValues(): array
    {
        $value = trim(($this->search?->value ?? ''));
        if ('' === $value) {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!\is_array($decoded)) {
            return [$value];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => trim((string) $item), $decoded),
            static fn (string $item): bool => '' !== $item,
        ));
    }
}
