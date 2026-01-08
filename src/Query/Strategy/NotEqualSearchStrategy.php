<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;

/**
 * Strategy for 'notEqual' search logic.
 *
 * Performs a non-equality comparison using SQL !=.
 */
final class NotEqualSearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        $field     = sprintf('%s.%s', $alias, $column->getField());
        $paramName = sprintf('column_control_param_%d', $paramIndex);

        $qb->andWhere(sprintf('%s != :%s', $field, $paramName));
        $qb->setParameter($paramName, $search->value);
    }

    public function getLogic(): string
    {
        return 'notEqual';
    }
}
