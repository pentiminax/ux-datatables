<?php

namespace Pentiminax\UX\DataTables\DataProvider;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Model\DataTableQuery;
use Pentiminax\UX\DataTables\Model\DataTableResult;

final class ArrayDataProvider implements DataProviderInterface
{
    /**
     * @param iterable<object|array> $items
     */
    public function __construct(
        private readonly iterable $items,
        private readonly RowMapperInterface $rowMapper
    ) {}

    public function fetchData(DataTableQuery $query): DataTableResult
    {
        $all = [];
        foreach ($this->items as $item) {
            $all[] = $item;
        }

        $filtered = $all;
        if ($query->globalSearch) {
            $globalSearch = mb_strtolower($query->globalSearch);
            $filtered = array_filter($all, function ($item) use ($globalSearch) {
                $row = $this->rowMapper->map(is_object($item) ? $item : (object) $item);
                return (bool) array_filter($row, static fn (mixed $value) => str_contains(mb_strtolower((string)$value), $globalSearch));
            });
        }

        $slice = array_slice(
            array_values($filtered), $query->start, $query->length
        );

        $rows = (function () use ($slice) {
            foreach ($slice as $item) {
                yield $this->rowMapper->map(is_object($item) ? $item : (object) $item);
            }
        })();

        return new DataTableResult(
            recordsTotal: \count($all),
            recordsFiltered: \count($filtered),
            data: $rows
        );
    }
}
