<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;

/**
 * A user-facing filter declared via AbstractDataTable::configureFilters().
 *
 * Each filter renders a control in the Stimulus-built filter bar and applies a
 * Doctrine condition server-side. The value comes from the AJAX request keyed by
 * the filter name. Filters must ignore empty/irrelevant values (no-op).
 */
interface FilterInterface extends \JsonSerializable
{
    /**
     * Unique filter name, used as the AJAX payload key (filters[name]).
     */
    public function getName(): string;

    /**
     * Apply the filter condition to the QueryBuilder for the given submitted value.
     *
     * Implementations must be a no-op when $value is empty or not applicable.
     */
    public function apply(QueryBuilder $qb, mixed $value, string $alias): void;

    /**
     * Client-side definition consumed by the Stimulus controller.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array;
}
