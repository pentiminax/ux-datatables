<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;

/**
 * Default factory that normalizes a DataTableRequest plus configured columns into a
 * provider-neutral {@see DataTableQueryIntent}.
 *
 * This is the single place that resolves raw DataTables request indexes. Configured
 * columns are authoritative; request-only columns never create intent. Malformed
 * transport-level inputs (unknown indexes, empty searches) are dropped to preserve
 * the current no-op behaviour rather than raising errors.
 */
final class DefaultDataTableQueryIntentFactory implements DataTableQueryIntentFactoryInterface
{
    public function create(DataTableRequest $request, array $columns): DataTableQueryIntent
    {
        $references = $this->buildReferences($columns);

        return new DataTableQueryIntent(
            draw: $request->draw,
            pagination: $this->buildPagination($request),
            columns: array_values($references),
            globalSearch: $this->buildGlobalSearch($request, $references),
            order: $this->buildOrder($request, $references),
            columnSearches: $this->buildColumnSearches($request, $references),
            columnControls: $this->buildColumnControls($request, $references),
        );
    }

    /**
     * @param list<ColumnInterface> $columns
     *
     * @return array<int, ColumnReadReference> keyed by display index
     */
    private function buildReferences(array $columns): array
    {
        $references = [];
        $seenNames  = [];

        foreach ($columns as $index => $column) {
            if (!$column instanceof ColumnInterface) {
                throw new InvalidQueryIntentException(\sprintf('Configured column at index %s must implement %s.', $index, ColumnInterface::class));
            }

            $name = $column->getName();
            if (isset($seenNames[$name])) {
                throw new InvalidQueryIntentException(\sprintf('Duplicate configured column name "%s".', $name));
            }

            $seenNames[$name] = true;

            $references[$index] = new ColumnReadReference(
                name: $name,
                fieldPath: $column->getField(),
                type: $column->getType(),
                searchable: $column->isSearchable(),
                globalSearchable: $column->isGlobalSearchable(),
                orderable: $column->isOrderable(),
            );
        }

        return $references;
    }

    private function buildPagination(DataTableRequest $request): PaginationIntent
    {
        $offset = max(0, $request->start);
        $limit  = $request->length > 0 ? $request->length : null;

        return new PaginationIntent($offset, $limit);
    }

    /**
     * @param array<int, ColumnReadReference> $references
     */
    private function buildGlobalSearch(DataTableRequest $request, array $references): ?GlobalSearchIntent
    {
        $hasGlobalSearchableColumn = false;
        foreach ($references as $reference) {
            if ($reference->globalSearchable) {
                $hasGlobalSearchableColumn = true;

                break;
            }
        }

        if (!$hasGlobalSearchableColumn) {
            return null;
        }

        $value = $request->search->value ?? '';
        if ('' === trim($value)) {
            return null;
        }

        return new GlobalSearchIntent($value, $request->search?->regex ?? false);
    }

    /**
     * @param array<int, ColumnReadReference> $references
     */
    private function buildOrder(DataTableRequest $request, array $references): ?OrderIntent
    {
        if (1 !== \count($request->order)) {
            return null;
        }

        $order     = $request->order[0];
        $reference = $references[$order->column] ?? null;

        if (null === $reference || !$reference->orderable) {
            return null;
        }

        return new OrderIntent($reference, SortDirection::fromRequest($order->dir));
    }

    /**
     * @param array<int, ColumnReadReference> $references
     *
     * @return list<ColumnSearchIntent>
     */
    private function buildColumnSearches(DataTableRequest $request, array $references): array
    {
        $searches = [];

        foreach ($references as $index => $reference) {
            if (!$reference->searchable || null === $reference->fieldPath) {
                continue;
            }

            $requestColumn = $request->columns->getColumnByIndex($index);
            if (null === $requestColumn) {
                continue;
            }

            $search = $requestColumn->search;
            if (null === $search || null === $search->value || '' === trim($search->value)) {
                continue;
            }

            $searches[] = new ColumnSearchIntent($reference, $search->value, $search->regex);
        }

        return $searches;
    }

    /**
     * @param array<int, ColumnReadReference> $references
     *
     * @return list<ColumnControlIntent>
     */
    private function buildColumnControls(DataTableRequest $request, array $references): array
    {
        $controls = [];

        foreach ($references as $index => $reference) {
            if (!$reference->searchable || null === $reference->fieldPath) {
                continue;
            }

            $requestColumn = $request->columns->getColumnByIndex($index);
            $columnControl = $requestColumn?->columnControl;

            if (null === $columnControl) {
                continue;
            }

            $intent = $this->buildColumnControlIntent($reference, $columnControl);
            if (null !== $intent) {
                $controls[] = $intent;
            }
        }

        return $controls;
    }

    private function buildColumnControlIntent(ColumnReadReference $reference, ColumnControl $columnControl): ?ColumnControlIntent
    {
        // List criteria win over scalar search, matching the current filter branch order.
        if ([] !== $columnControl->list) {
            return new ColumnControlIntent(
                column: $reference,
                logic: ColumnControlLogic::In,
                valueType: '',
                values: array_values($columnControl->list),
            );
        }

        $search = $columnControl->search;
        if (null === $search) {
            return null;
        }

        // Nullness logics (empty/notEmpty) test the field, not the search value, so an
        // empty value is meaningful for them. Value-consuming logics drop empty values,
        // matching the no-op behaviour of their strategies.
        if (!$this->isNullnessLogic($search->logic) && '' === trim($search->value)) {
            return null;
        }

        return new ColumnControlIntent(
            column: $reference,
            logic: $search->logic,
            valueType: $search->type,
            value: $search->value,
        );
    }

    private function isNullnessLogic(ColumnControlLogic $logic): bool
    {
        return ColumnControlLogic::Empty === $logic || ColumnControlLogic::NotEmpty === $logic;
    }
}
