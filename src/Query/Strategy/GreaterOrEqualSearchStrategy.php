<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for 'greaterOrEqual' search logic.
 *
 * Performs a greater-than-or-equal comparison using SQL >=.
 */
final class GreaterOrEqualSearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        $field     = RelationFieldResolver::resolve($qb, $alias, $column->getField());
        $paramName = \sprintf('column_control_param_%d', $paramIndex);

        $qb->andWhere(\sprintf('%s >= :%s', $field, $paramName));
        $qb->setParameter($paramName, $search->value);
    }

    public function getLogic(): string
    {
        return 'greaterOrEqual';
    }
}
