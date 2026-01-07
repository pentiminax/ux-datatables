<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;

/**
 * Strategy for 'less' search logic.
 *
 * Performs a less-than comparison using SQL <.
 */
final class LessThanSearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        $field     = sprintf('%s.%s', $alias, $column->getName());
        $paramName = sprintf('column_control_param_%d', $paramIndex);

        $qb->andWhere(sprintf('%s < :%s', $field, $paramName));
        $qb->setParameter($paramName, $search->value);
    }

    public function getLogic(): string
    {
        return 'less';
    }
}
