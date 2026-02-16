<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for 'in' search logic.
 *
 * Performs SQL IN clause for list values.
 */
final class InListSearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        // This strategy is used differently - it's called from ColumnControlSearchFilter
        // when list values are present, not through the standard search flow
    }

    public function getLogic(): string
    {
        return 'in';
    }

    /**
     * Apply IN clause for list values.
     */
    public function applyForList(QueryBuilder $qb, string $columnField, array $values, string $alias): void
    {
        if (empty($values)) {
            return;
        }

        $field     = RelationFieldResolver::resolve($qb, $alias, $columnField);
        $paramName = \sprintf(':%s_in', str_replace('.', '_', $columnField));

        $qb->andWhere(\sprintf('%s IN (%s)', $field, $paramName));
        $qb->setParameter($paramName, $values);
    }
}
