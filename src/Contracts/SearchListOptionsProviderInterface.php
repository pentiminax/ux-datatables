<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

interface SearchListOptionsProviderInterface
{
    /**
     * Return null to omit this column from the Ajax `columnControl` object.
     *
     * Options may be scalar values, BackedEnum cases, `[label => value]` entries, or
     * arrays with explicit `label` and `value` keys.
     *
     * @return iterable<array-key, scalar|\BackedEnum|array{label: scalar, value: scalar}>|null
     */
    public function provide(DataTableRequest $request, ColumnInterface $column): ?iterable;
}
