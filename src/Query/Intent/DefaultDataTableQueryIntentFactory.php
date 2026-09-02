<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Exception\InvalidQueryIntentException;

/**
 * Default factory that normalizes a DataTableRequest plus configured columns into a
 * provider-neutral {@see DataTableQueryIntent}.
 *
 * This is the single place that resolves raw DataTables request columns onto the
 * configured table. Configured columns are authoritative; request-only columns never
 * create intent. Matching is by column name, not display index: the client may prepend
 * columns (Select checkbox mode unshifts one) that the server never configured.
 * Malformed transport-level inputs (unknown names, empty searches) are dropped to
 * preserve the current no-op behaviour rather than raising errors.
 */
final class DefaultDataTableQueryIntentFactory
{
    /**
     * @param list<ColumnInterface> $columns configured, permission-filtered columns in display order
     *
     * @throws InvalidQueryIntentException for impossible programmer/configuration states only
     */
    public function create(DataTableRequest $request, array $columns): DataTableQueryIntent
    {
        $references               = $this->buildReferences($columns);
        [$orderColumn, $orderDir] = $this->buildOrder($request, $references);

        return new DataTableQueryIntent(
            draw: $request->draw,
            offset: max(0, $request->start),
            limit: $request->length > 0 ? $request->length : null,
            columns: array_values($references),
            globalSearch: $this->buildGlobalSearch($request, $references),
            orderColumn: $orderColumn,
            orderDir: $orderDir,
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

    /**
     * @param array<int, ColumnReadReference> $references
     */
    private function buildGlobalSearch(DataTableRequest $request, array $references): ?string
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

        $value = trim($request->search->value ?? '');
        if ('' === $value) {
            return null;
        }

        return $value;
    }

    /**
     * @param array<int, ColumnReadReference> $references
     *
     * @return array{0: ?ColumnReadReference, 1: ?string}
     */
    private function buildOrder(DataTableRequest $request, array $references): array
    {
        if (1 !== \count($request->order)) {
            return [null, null];
        }

        $order     = $request->order[0];
        $reference = $this->referenceByName($references, $order->name);

        if (null === $reference || !$reference->orderable) {
            return [null, null];
        }

        $orderDir = 'desc' === strtolower(trim($order->dir)) ? 'desc' : 'asc';

        return [$reference, $orderDir];
    }

    /**
     * @param array<int, ColumnReadReference> $references
     *
     * @return list<array{column: ColumnReadReference, value: string}>
     */
    private function buildColumnSearches(DataTableRequest $request, array $references): array
    {
        $searches = [];

        foreach ($references as $reference) {
            if (!$reference->searchable || null === $reference->fieldPath) {
                continue;
            }

            $requestColumn = $request->columns->getColumnByName($reference->name);
            if (null === $requestColumn) {
                continue;
            }

            $search = $requestColumn->search;
            if (null === $search || null === $search->value || '' === trim($search->value)) {
                continue;
            }

            $searches[] = ['column' => $reference, 'value' => $search->value];
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

        foreach ($references as $reference) {
            if (!$reference->searchable || null === $reference->fieldPath) {
                continue;
            }

            $requestColumn = $request->columns->getColumnByName($reference->name);
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

    /**
     * @param array<int, ColumnReadReference> $references
     */
    private function referenceByName(array $references, string $name): ?ColumnReadReference
    {
        if ('' === $name) {
            return null;
        }

        foreach ($references as $reference) {
            if ($reference->name === $name) {
                return $reference;
            }
        }

        return null;
    }
}
