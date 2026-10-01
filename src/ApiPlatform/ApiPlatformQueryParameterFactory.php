<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Intent\ColumnReadReference;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;

/**
 * Translates a normalized read intent into API Platform collection query parameters.
 *
 * The browser-side adapter performs the same translation for tables the browser queries
 * directly. Both implementations must stay aligned: the shared cases live in
 * tests/Fixtures/api-platform-query-parameters.json and are replayed by the PHP and the
 * TypeScript suites.
 *
 * Protocol parameters win: a configured filter named "page", "itemsPerPage" or "q", or one
 * colliding with an order or column-search key, never overwrites the key already set.
 *
 * Not final: doubled in tests, like ColumnAutoDetector.
 */
class ApiPlatformQueryParameterFactory
{
    private const GLOBAL_SEARCH_PARAMETER = 'q';

    /**
     * Pagination is left out when the intent carries no limit: an export owns its own paging.
     *
     * @return array<string, string|array<int|string, string>>
     */
    public function create(DataTableQueryIntent $intent, DataTableRequest $request): array
    {
        $parameters = [];

        if (null !== $intent->limit) {
            $parameters['page']         = (string) (intdiv($intent->offset, $intent->limit) + 1);
            $parameters['itemsPerPage'] = (string) $intent->limit;
        }

        if (null !== $intent->globalSearch) {
            $parameters[self::GLOBAL_SEARCH_PARAMETER] = $intent->globalSearch;
        }

        $this->appendOrder($parameters, $intent, $request);

        foreach ($intent->columnSearches as $columnSearch) {
            $this->set($parameters, $this->fieldName($columnSearch['column']), $columnSearch['value']);
        }

        $this->appendFilters($parameters, $request->filters);

        return $parameters;
    }

    /**
     * DataTables sends ordering priority as an ordered list, and API Platform honors the key
     * order of order[...] parameters, so multi-column sorting survives the translation.
     *
     * @param array<string, string|array<int|string, string>> $parameters
     */
    private function appendOrder(array &$parameters, DataTableQueryIntent $intent, DataTableRequest $request): void
    {
        foreach ($request->order as $order) {
            $column = $this->columnByName($intent, $order->name);

            if (null === $column || !$column->orderable) {
                continue;
            }

            $direction = 'desc' === strtolower(trim($order->dir)) ? 'desc' : 'asc';

            $this->set($parameters, \sprintf('order[%s]', $this->fieldName($column)), $direction);
        }
    }

    /**
     * @param array<string, string|array<int|string, string>> $parameters
     * @param array<string, mixed>                            $filters
     */
    private function appendFilters(array &$parameters, array $filters): void
    {
        foreach ($filters as $name => $value) {
            if (!\is_string($name) || '' === $name) {
                continue;
            }

            if (\is_scalar($value)) {
                $scalar = (string) $value;

                if ('' !== trim($scalar)) {
                    $this->set($parameters, $name, $scalar);
                }

                continue;
            }

            if (!\is_array($value)) {
                continue;
            }

            if (array_is_list($value)) {
                $this->appendListFilter($parameters, $name, $value);

                continue;
            }

            $this->appendRangeFilter($parameters, $name, $value);
        }
    }

    /**
     * The index only advances for kept entries, so a blank value leaves no gap in the list.
     *
     * @param array<string, string|array<int|string, string>> $parameters
     * @param list<mixed>                                     $values
     */
    private function appendListFilter(array &$parameters, string $name, array $values): void
    {
        $index = 0;

        foreach ($values as $value) {
            if (!\is_scalar($value)) {
                continue;
            }

            $scalar = (string) $value;
            if ('' === trim($scalar)) {
                continue;
            }

            $this->set($parameters, \sprintf('%s[%d]', $name, $index), $scalar);
            ++$index;
        }
    }

    /**
     * A {from, to} filter maps onto API Platform's DateFilter bounds.
     *
     * Date-only `to` becomes `strictly_before` of the next calendar day: API Platform's
     * inclusive `before=YYYY-MM-DD` is midnight of that day, which would drop every later
     * timestamp on the selected end day — the same trap {@see \Pentiminax\UX\DataTables\Filter\DateRangeFilter}
     * already avoids on the Doctrine path.
     *
     * @param array<string, string|array<int|string, string>> $parameters
     * @param array<array-key, mixed>                         $value
     */
    private function appendRangeFilter(array &$parameters, string $name, array $value): void
    {
        $from = $value['from'] ?? null;
        if (\is_scalar($from)) {
            $fromBound = trim((string) $from);
            if ('' !== $fromBound) {
                $this->set($parameters, \sprintf('%s[after]', $name), $fromBound);
            }
        }

        $to = $value['to'] ?? null;
        if (!\is_scalar($to)) {
            return;
        }

        $toBound = trim((string) $to);
        if ('' === $toBound) {
            return;
        }

        $exclusiveTo = $this->exclusiveNextDayUpperBound($toBound);
        if (null !== $exclusiveTo) {
            $this->set($parameters, \sprintf('%s[strictly_before]', $name), $exclusiveTo);

            return;
        }

        $this->set($parameters, \sprintf('%s[before]', $name), $toBound);
    }

    /**
     * Next calendar day for a date-only HTML bound, or null when the value carries a time
     * (kept as inclusive `before`) or is not a calendar date.
     */
    private function exclusiveNextDayUpperBound(string $rawTo): ?string
    {
        if (1 !== preg_match('/^(\d{4})[-\/](\d{1,2})[-\/](\d{1,2})$/', $rawTo, $matches)) {
            return null;
        }

        $normalized = \sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        $date       = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);

        if (false === $date || $date->format('Y-m-d') !== $normalized) {
            return null;
        }

        return $date->add(new \DateInterval('P1D'))->format('Y-m-d');
    }

    /**
     * @param array<string, string|array<int|string, string>> $parameters
     */
    private function set(array &$parameters, string $key, string $value): void
    {
        if (\array_key_exists($key, $parameters)) {
            return;
        }

        $parameters[$key] = $value;
    }

    private function columnByName(DataTableQueryIntent $intent, string $name): ?ColumnReadReference
    {
        if ('' === $name) {
            return null;
        }

        foreach ($intent->columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        return null;
    }

    private function fieldName(ColumnReadReference $column): string
    {
        return $column->fieldPath ?? $column->name;
    }
}
