<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\Rendering\PropertyReader;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Intent\ColumnReadReference;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;

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
 * SearchBuilder criteria, ColumnControl searches and configured filter values are not
 * implemented in memory; {@see self::supports()} declares it, and the bundle answers such a
 * request with HTTP 400 instead of returning unfiltered rows.
 */
final class ArrayDataProvider implements DataProviderInterface, StreamingDataProviderInterface, FeatureAwareDataProviderInterface
{
    /**
     * @param iterable<object|array> $items
     * @param list<ColumnInterface>  $columns configured, permission-filtered columns -- the list
     *                                        {@see \Pentiminax\UX\DataTables\Model\AbstractDataTable::getResolvedColumns()}
     *                                        returns. Left empty, nothing is orderable, and a
     *                                        request carrying a search term raises.
     * @param Filters|null           $filters @deprecated since 1.3, the bundle now rejects a value
     *                                        for any of the table's configured filters on its own.
     *                                        Removed in 2.0.
     */
    public function __construct(
        private readonly iterable $items,
        private readonly RowMapperInterface $rowMapper,
        private readonly array $columns = [],
        ?Filters $filters = null,
        private readonly DefaultDataTableQueryIntentFactory $intentFactory = new DefaultDataTableQueryIntentFactory(),
    ) {
        if (null !== $filters) {
            trigger_deprecation('pentiminax/ux-datatables', '1.3', 'Passing $filters to "%s" is deprecated and removed in 2.0: the bundle rejects configured filter values on its own.', self::class);
        }
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return DataSourceFeature::Summaries === $feature;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return $this->query($request, true);
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return $this->query($request->withoutPagination(), false)->data;
    }

    private function query(DataTableRequest $request, bool $summarize): DataTableResult
    {
        $intent = $this->intentFactory->create($request, $this->columns);

        // Without columns nothing is searchable, so a term would silently match every row and
        // report recordsFiltered == recordsTotal. Say so instead of answering unfiltered.
        if ([] === $this->columns && '' !== trim($request->search?->value ?? '')) {
            throw new \LogicException('ArrayDataProvider was built without columns, so it cannot search. Pass $this->getResolvedColumns() as its third argument.');
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
            summaries: $summarize ? $this->summarize($matched) : null,
        );
    }

    /**
     * @param list<object> $items
     *
     * @return array<string, array<string, int|float|string|null>>|null
     */
    private function summarize(array $items): ?array
    {
        $summaries = [];

        foreach ($this->columns as $column) {
            if (!$column instanceof AbstractColumn) {
                continue;
            }

            $values = [] === $column->getSummarizers() ? [] : array_map(
                static fn (object $item): mixed => PropertyReader::readPath($item, $column->getField() ?? $column->getName()),
                $items,
            );

            foreach ($column->getSummarizers() as $summarizer) {
                $summaries[$column->getName()][$summarizer->getType()->value] = $summarizer->getType()->aggregate($values);
            }
        }

        return [] === $summaries ? null : $summaries;
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

        // Same eligibility as the Doctrine GlobalSearchFilter: isSearchable() only governs the
        // per-column search box, and a term no column can carry filters nothing rather than
        // everything.
        $globalColumns = array_values(array_filter(
            $intent->columns,
            static fn (ColumnReadReference $column): bool => $column->globalSearchable,
        ));

        $matched = [];
        foreach ($items as $item) {
            if (null !== $globalSearch && [] !== $globalColumns && !$this->matchesAny($item, $globalColumns, $globalSearch)) {
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
        $orders = $intent->orderCriteria();

        if ([] === $orders) {
            return;
        }

        usort($items, function (object $left, object $right) use ($orders): int {
            foreach ($orders as $order) {
                $comparison = $this->compare($left, $right, $order['column'], 'desc' === $order['dir']);

                if (0 !== $comparison) {
                    return $comparison;
                }
            }

            return 0;
        });
    }

    private function compare(object $left, object $right, ColumnReadReference $column, bool $descending): int
    {
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
    }

    /**
     * A column with no matching source property -- an ActionColumn, a TemplateColumn, a value that
     * only exists after mapping -- has no value to search or order by, so it reads as null.
     */
    private function readValue(object $item, ColumnReadReference $column): mixed
    {
        $path  = $column->fieldPath ?? $column->name;
        $value = PropertyReader::readPath($item, $path);

        return $value instanceof \BackedEnum ? $value->value : $value;
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
