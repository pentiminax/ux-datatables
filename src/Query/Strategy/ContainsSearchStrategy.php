<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;

/**
 * Strategy for 'contains' search logic.
 *
 * Performs a case-sensitive substring search using SQL LIKE %value%.
 * For numeric columns, performs exact match if the value is numeric.
 *
 * Predicate construction is delegated to {@see SearchPredicateFactory} so the
 * "numeric → exact / date → skip / text → LIKE" branching lives in a single place.
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

        $field = $column->getField();
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
