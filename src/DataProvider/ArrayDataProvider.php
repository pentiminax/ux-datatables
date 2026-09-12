<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Intent\ColumnReadReference;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Serves a page from an in-memory collection, honoring the request through the same
 * {@see DataTableQueryIntent} the Doctrine path consumes.
 *
 * Ordering and search read the *source* value of each item -- the property named by the
 * column's field path -- not the mapped row, so the semantics match Doctrine's: a column
 * with no matching property (an ActionColumn, a TemplateColumn) is simply not searchable.
 * Search is case-insensitive and the term is trimmed. `getOrderExpression()` is DQL and is
 * ignored here.
 *
 * ColumnControl searches and configured {@see Filters} are
 * not implemented in memory: a request carrying either raises instead of silently returning
 * unfiltered rows with HTTP 200.
 */
final class ArrayDataProvider implements DataProviderInterface, StreamingDataProviderInterface
{
    /**
     * @param iterable<object|array> $items
     * @param list<ColumnInterface>  $columns configured, permission-filtered columns -- the list
     *                                        {@see \Pentiminax\UX\DataTables\Model\AbstractDataTable::getResolvedColumns()}
     *                                        returns. Left empty, nothing is orderable or searchable.
     * @param Filters|null           $filters the table's configured filters, so a request carrying a
     *                                        value for one of them is rejected. Left null, filter values
     *                                        are ignored like the Doctrine provider ignores them.
     */
    public function __construct(
        private readonly iterable $items,
        private readonly RowMapperInterface $rowMapper,
        private readonly array $columns = [],
        private readonly ?Filters $filters = null,
        private readonly DefaultDataTableQueryIntentFactory $intentFactory = new DefaultDataTableQueryIntentFactory(),
        private readonly PropertyAccessorInterface $propertyAccessor = new PropertyAccessor(),
    ) {
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $intent = $this->intentFactory->create($request, $this->columns);

        if ([] !== $intent->columnControls || $this->hasActiveFilters($request)) {
            throw new \LogicException('ArrayDataProvider does not support ColumnControl searches or configured Filters. Use a DoctrineDataProvider or a custom DataProviderInterface for this table.');
        }

        $all = [];
        foreach ($this->items as $item) {
            $all[] = $this->normalize($item);
        }

        $recordsTotal = \count($all);

        $matched = $this->filter($all, $intent);
        $this->sort($matched, $intent);

        // Out-of-page rows are never mapped: filtering and ordering read the source items,
        // so only the returned slice reaches the row mapper.
        return new DataTableResult(
            recordsTotal: $recordsTotal,
            recordsFiltered: \count($matched),
            data: $this->mapRows($this->slice($matched, $request)),
        );
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return $this->fetchData($request->withoutPagination())->data;
    }

    /**
     * Only configured filter names count, and emptiness is tested exactly as
     * {@see \Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline} tests it, so the same
     * request is "filtered" for both providers: an unknown key is ignored, not rejected.
     */
    private function hasActiveFilters(DataTableRequest $request): bool
    {
        if (null === $this->filters) {
            return false;
        }

        foreach ($this->filters->getFilters() as $filter) {
            $value = $request->filters[$filter->getName()] ?? null;

            if (null !== $value && '' !== $value && [] !== $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<object> $items
     *
     * @return list<object>
     */
    private function filter(array $items, DataTableQueryIntent $intent): array
    {
        $globalSearch = $intent->globalSearch;

        if (null === $globalSearch && [] === $intent->columnSearches) {
            return $items;
        }

        $globalColumns = array_values(array_filter(
            $intent->columns,
            static fn (ColumnReadReference $column): bool => $column->searchable && $column->globalSearchable,
        ));

        $matched = [];
        foreach ($items as $item) {
            if (null !== $globalSearch && !$this->matchesAny($item, $globalColumns, $globalSearch)) {
                continue;
            }

            foreach ($intent->columnSearches as $columnSearch) {
                if (!$this->matches($item, $columnSearch['column'], $columnSearch['value'])) {
                    continue 2;
                }
            }

            $matched[] = $item;
        }

        return $matched;
    }

    /**
     * @param list<ColumnReadReference> $columns
     */
    private function matchesAny(object $item, array $columns, string $needle): bool
    {
        foreach ($columns as $column) {
            if ($this->matches($item, $column, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function matches(object $item, ColumnReadReference $column, string $needle): bool
    {
        $haystack = $this->searchableText($this->readValue($item, $column));
        $needle   = trim($needle);

        if (null === $haystack || '' === $needle) {
            return false;
        }

        return str_contains(mb_strtolower($haystack), mb_strtolower($needle));
    }

    /**
     * Values a LIKE would not reach in Doctrine either: null, booleans, dates and anything
     * that is not a string (nested action/URL arrays included) are not searchable.
     */
    private function searchableText(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return null;
        }

        return match (true) {
            \is_string($value)                 => $value,
            \is_int($value), \is_float($value) => (string) $value,
            $value instanceof \Stringable      => (string) $value,
            default                            => null,
        };
    }

    /**
     * @param list<object> $items
     */
    private function sort(array &$items, DataTableQueryIntent $intent): void
    {
        $column = $intent->orderColumn;

        if (null === $column) {
            return;
        }

        $descending = 'desc' === $intent->orderDir;

        usort($items, function (object $left, object $right) use ($column, $descending): int {
            $leftValue  = $this->readValue($left, $column);
            $rightValue = $this->readValue($right, $column);

            // Rows without a value sort last in both directions rather than flooding the
            // first page of a descending sort.
            if (null === $leftValue || null === $rightValue) {
                return (null === $leftValue ? 1 : 0) <=> (null === $rightValue ? 1 : 0);
            }

            $comparison = \is_string($leftValue) && \is_string($rightValue)
                ? strnatcasecmp($leftValue, $rightValue)
                : $leftValue <=> $rightValue;

            return $descending ? -$comparison : $comparison;
        });
    }

    /**
     * A column with no matching source property -- an ActionColumn, a TemplateColumn, a value that
     * only exists after mapping -- has no value to search or order by, so it reads as null.
     */
    private function readValue(object $item, ColumnReadReference $column): mixed
    {
        $path = $column->fieldPath ?? $column->name;

        if (!$this->propertyAccessor->isReadable($item, $path)) {
            return null;
        }

        return $this->propertyAccessor->getValue($item, $path);
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
     * @param list<object> $items
     *
     * @return \Generator<array>
     */
    private function mapRows(array $items): \Generator
    {
        foreach ($items as $item) {
            yield $this->rowMapper->map($item);
        }
    }

    private function normalize(mixed $item): object
    {
        return \is_object($item) ? $item : (object) $item;
    }
}
