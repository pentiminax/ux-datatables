<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for 'contains' search logic.
 *
 * Performs a case-sensitive substring search using SQL LIKE %value%.
 * For numeric columns, performs exact match if the value is numeric.
 *
 * Predicate construction is delegated to {@see SearchPredicateBuilderInterface} so the
 * "numeric → exact / date → skip / text → LIKE" branching lives in a single place.
 * In addition to the column's own type, a search type hint of number/numeric/num
 * forces numeric handling.
 *
 * The column's declared search joins are applied first, and its
 * {@see ColumnInterface::getSearchField()} override replaces getField() when set. A column
 * that builds its own predicate short-circuits the whole type dispatch -- see
 * {@see SearchPredicateBuilderInterface}.
 */
final class ContainsSearchStrategy implements SearchStrategyInterface
{
    private const array NUMERIC_TYPE_HINTS = ['number', 'numeric', 'num'];

    public function __construct(
        private readonly SearchPredicateBuilderInterface $predicateBuilder = new DefaultSearchPredicateBuilder(),
    ) {
    }

    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        RelationFieldResolver::applySearchJoins($qb, $column);

        $field = $column->getSearchField() ?? $column->getField();
        if (null === $field) {
            return;
        }

        $paramName    = \sprintf('column_control_param_%d', $paramIndex);
        $forceNumeric = \in_array(strtolower($search->type), self::NUMERIC_TYPE_HINTS, true);

        $predicate = $this->predicateBuilder->build($qb, $column, $alias, $field, $search->value, $paramName, $forceNumeric);

        if (null !== $predicate) {
            $qb->andWhere($predicate);
        }
    }

    public function getLogic(): string
    {
        return 'contains';
    }
}
