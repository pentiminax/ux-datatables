<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * A single requested ordering.
 *
 * Only one order is emitted to preserve current single-column ordering compatibility.
 */
final readonly class OrderIntent
{
    public function __construct(
        public ColumnReadReference $column,
        public SortDirection $direction,
    ) {
    }
}
