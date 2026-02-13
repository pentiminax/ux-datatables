<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;

/**
 * Strategy for 'empty' search logic.
 *
 * Checks for NULL values (and empty strings for text columns).
 * For numeric columns, only checks IS NULL.
 * For text columns, checks IS NULL OR = ''.
 */
final class EmptySearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        $field = \sprintf('%s.%s', $alias, $column->getName());
        $expr  = $qb->expr();

        $isNumeric = $column->isNumber() || \in_array(strtolower($search->type), ['number', 'numeric', 'num'], true);

        if ($isNumeric) {
            $qb->andWhere($expr->isNull($field));
        } else {
            $qb->andWhere($expr->orX(
                $expr->isNull($field),
                $expr->eq($field, $expr->literal(''))
            ));
        }
    }

    public function getLogic(): string
    {
        return 'empty';
    }
}
