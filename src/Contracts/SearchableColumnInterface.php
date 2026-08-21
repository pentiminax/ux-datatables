<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;

/**
 * Opt-in contract for columns that need custom search predicate logic.
 *
 * Implement this interface to take full control over how a column's value is
 * searched — for example, to match against multiple fields, apply a custom
 * DQL function, or build a predicate the default LIKE strategy cannot express.
 *
 * The predicate returned by {@see buildSearchPredicate()} is a raw DQL fragment.
 * Bind any parameters you need directly on the supplied QueryBuilder.
 * Returning null signals that this column should be skipped for the given search
 * value (e.g. when the value is not meaningful for this column's type).
 *
 * The filter that calls this method ({@see GlobalSearchFilter} or
 * {@see ColumnSearchFilter}) decides how the fragment is composed into the
 * WHERE clause — global search OR's all column fragments together; column search
 * AND's each fragment.
 *
 * {@see AbstractColumn::setSearchPredicate()} provides a fluent closure-based
 * alternative so users do not need to subclass a column type.
 */
interface SearchableColumnInterface
{
    /**
     * Build a DQL predicate fragment for a search value.
     *
     * @param QueryBuilder $qb        The query builder (add parameters here)
     * @param string       $alias     The root entity alias (e.g. 'e')
     * @param string       $value     The raw search value from the request
     * @param string       $paramName A unique parameter name for this column/search pair
     *
     * @return string|null A DQL condition string, or null to skip this column
     */
    public function buildSearchPredicate(
        QueryBuilder $qb,
        string $alias,
        string $value,
        string $paramName,
    ): ?string;
}
