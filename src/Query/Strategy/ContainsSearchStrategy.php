<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;

/**
 * Strategy for 'contains' search logic.
 *
 * Performs a case-sensitive substring search using SQL LIKE %value%.
 * For numeric columns, performs exact match if the value is numeric.
 */
final class ContainsSearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        $field     = \sprintf('%s.%s', $alias, $column->getField());
        $paramName = \sprintf('column_control_param_%d', $paramIndex);

        $isNumeric = $column->isNumber()
                     || \in_array(strtolower($search->type), ['number', 'numeric', 'num'], true);

        if ($isNumeric) {
            if (!is_numeric($search->value)) {
                return;
            }
            $qb->andWhere(\sprintf('%s = :%s', $field, $paramName));
            $qb->setParameter($paramName, $search->value);
        } else {
            $qb->andWhere(\sprintf('%s LIKE :%s', $field, $paramName));
            $qb->setParameter($paramName, \sprintf('%%%s%%', $search->value));
        }
    }

    public function getLogic(): string
    {
        return 'contains';
    }
}
