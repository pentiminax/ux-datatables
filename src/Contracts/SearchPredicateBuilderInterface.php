<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;

/**
 * Builds a DQL search condition for a column based on its type, returning it for the caller
 * to compose rather than applying it to the QueryBuilder directly.
 *
 * Unlike {@see SearchStrategyInterface}, which mutates the QueryBuilder in place and so only
 * fits AND-composed criteria, implementations here must not call QueryBuilder::andWhere():
 * GlobalSearchFilter collects one condition per globally searchable column and combines them
 * with OR, so the composition decision belongs to the caller.
 */
interface SearchPredicateBuilderInterface
{
    /**
     * Returns the DQL condition for $field, or null when $value cannot be searched against it
     * (an unparseable value for the column's Doctrine type, or a type LIKE cannot be used on).
     * Binds $paramName on $qb as a side effect when a condition is returned.
     */
    public function build(
        QueryBuilder $qb,
        ColumnInterface $column,
        string $alias,
        string $field,
        string $value,
        string $paramName,
        bool $forceNumeric = false,
    ): ?string;
}
