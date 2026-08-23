<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchAwareColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\ColumnSearchResolver;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;

/**
 * Strategy for 'contains' search logic.
 *
 * Performs a case-insensitive substring search via UX_DATATABLES_SEARCH.
 * For numeric columns, performs exact match if the value is numeric.
 *
 * Resolution order:
 *
 *  1. Any search joins declared on the column are applied first.
 *  2. {@see SearchPredicateFactory::build()} is called with the effective field
 *     path (from {@see SearchAwareColumnInterface::getSearchField()} or
 *     {@see ColumnInterface::getField()}). That factory also delegates to the
 *     column's {@see \Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface}
 *     custom predicate when one is set.
 *
 * In addition to the column's own type, a search type hint of number/numeric/num
 * forces numeric handling.
 */
final class ContainsSearchStrategy implements SearchStrategyInterface
{
    private const array NUMERIC_TYPE_HINTS = ['number', 'numeric', 'num'];

    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        // Apply any column-declared search joins before resolving the predicate.
        ColumnSearchResolver::applySearchJoins($qb, $column);

        $field = ColumnSearchResolver::resolveField($column);
        if (null === $field) {
            return;
        }

        $paramName    = \sprintf('column_control_param_%d', $paramIndex);
        $forceNumeric = \in_array(strtolower($search->type), self::NUMERIC_TYPE_HINTS, true);

        $predicate = SearchPredicateFactory::build($qb, $column, $alias, $field, $search->value, $paramName, $forceNumeric);

        if (null !== $predicate) {
            $qb->andWhere($predicate);
        }
    }

    public function getLogic(): string
    {
        return 'contains';
    }
}
