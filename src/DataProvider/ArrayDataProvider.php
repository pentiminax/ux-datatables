<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;

final class ArrayDataProvider implements DataProviderInterface
{
    /**
     * @param iterable<object|array> $items
     */
    public function __construct(
        private readonly iterable $items,
        private readonly RowMapperInterface $rowMapper,
    ) {
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $all = [];
        foreach ($this->items as $item) {
            $all[] = $item;
        }

        $recordsTotal = \count($all);

        // An empty search value (the default sent on every unfiltered page load)
        // matches every row, so it is treated as "no search" to avoid mapping
        // out-of-page rows. Whitespace is preserved: only '' short-circuits.
        $term = $request->search?->value ?? '';

        // With an active search every element must be mapped to be searchable.
        // Map each element exactly once, then reuse those mapped rows for output.
        if ('' !== $term) {
            $matched = $this->filterBySearch($all, mb_strtolower($term));

            return new DataTableResult(
                recordsTotal: $recordsTotal,
                recordsFiltered: \count($matched),
                data: $this->slice($matched, $request),
            );
        }

        // Without a search, out-of-page rows must never be mapped: slice first,
        // then map only the paginated slice.
        return new DataTableResult(
            recordsTotal: $recordsTotal,
            recordsFiltered: $recordsTotal,
            data: $this->mapRows($this->slice($all, $request)),
        );
    }

    /**
     * @param list<object|array> $items
     *
     * @return list<array> the mapped rows matching the search, mapped exactly once each
     */
    private function filterBySearch(array $items, string $needle): array
    {
        $matched = [];
        foreach ($items as $item) {
            $row = $this->rowMapper->map($this->normalize($item));
            if ($this->rowMatches($row, $needle)) {
                $matched[] = $row;
            }
        }

        return $matched;
    }

    private function rowMatches(array $row, string $needle): bool
    {
        foreach ($row as $value) {
            if (str_contains(mb_strtolower((string) $value), $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @template T
     *
     * @param list<T> $items
     *
     * @return list<T>
     */
    private function slice(array $items, DataTableRequest $request): array
    {
        return $request->length > 0
            ? \array_slice($items, $request->start, $request->length)
            : \array_slice($items, $request->start);
    }

    /**
     * @param list<object|array> $items
     *
     * @return \Generator<array>
     */
    private function mapRows(array $items): \Generator
    {
        foreach ($items as $item) {
            yield $this->rowMapper->map($this->normalize($item));
        }
    }

    private function normalize(mixed $item): object
    {
        return \is_object($item) ? $item : (object) $item;
    }
}
