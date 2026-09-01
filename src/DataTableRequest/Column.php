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
            data: self::stringField($data['data'] ?? null),
            name: self::stringField($data['name'] ?? null),
            searchable: self::isTrueFlag($data['searchable'] ?? null),
            orderable: self::isTrueFlag($data['orderable'] ?? null),
            search: isset($data['search'])               && \is_array($data['search']) ? Search::fromArray($data['search']) : null,
            columnControl: isset($data['columnControl']) && \is_array($data['columnControl'])
                ? ColumnControl::fromArray($data['columnControl'])
                : null,
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
        $value = trim($this->search?->value ?? '');
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

    /**
     * Select's checkbox column is unshifted with `name: null` and `data: null`. The export
     * form serializer used to omit those keys entirely, and a TypeError on this constructor
     * aborted the download. Empty strings keep the request-column slot without matching any
     * configured column.
     */
    private static function stringField(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * DataTables sends these as the strings "true"/"false" on a form-urlencoded request.
     * Boolean `true` is accepted too: PHP request fixtures and a JSON body both use it.
     */
    private static function isTrueFlag(mixed $value): bool
    {
        return true === $value || 'true' === $value;
    }
}
