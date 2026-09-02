<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;

/**
 * Applies one ColumnControl search logic (`equal`, `starts`, `empty`, ...) to a single column.
 *
 * Unlike {@see SearchPredicateBuilderInterface}, a strategy mutates the QueryBuilder in place, so
 * it only fits criteria that are AND-composed with the rest of the query. Implementations must be
 * a no-op for a value they cannot use -- an empty term, a column with no field, a term that does
 * not parse for the field's Doctrine type -- rather than binding a value the driver will reject,
 * and must name every parameter from the $paramIndex they were given so two searches on the same
 * column cannot collide.
 *
 * getLogic() returns the request's logic identifier, which is also the key
 * {@see \Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry} indexes on. Register
 * a strategy by overriding AbstractDataTable::createSearchStrategyRegistry().
 */
interface SearchStrategyInterface
{
    /**
     * Apply search logic to the QueryBuilder for a specific column.
     */
    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void;

    public function getLogic(): string;
}
