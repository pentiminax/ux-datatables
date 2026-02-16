<?php

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for 'notEmpty' search logic.
 *
 * Checks for non-NULL values (and non-empty strings for text columns).
 * For numeric columns, only checks IS NOT NULL.
 * For text columns, checks IS NOT NULL AND != ''.
 */
final class NotEmptySearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        $field     = RelationFieldResolver::resolve($qb, $alias, $column->getField());
        $expr      = $qb->expr();
        $isNumeric = $column->isNumber() || \in_array(strtolower($search->type), ['number', 'numeric', 'num'], true);

        if ($isNumeric) {
            $qb->andWhere($expr->isNotNull($field));
        } else {
            $qb->andWhere($expr->andX(
                $expr->isNotNull($field),
                $expr->neq($field, $expr->literal(''))
            ));
        }
    }

    public function getLogic(): string
    {
        return 'notEmpty';
    }
}
