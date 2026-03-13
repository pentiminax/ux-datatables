<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for null/empty search logic.
 *
 * For numeric columns, only checks NULL / NOT NULL.
 * For text columns, also includes empty-string checks.
 */
final class NullnessSearchStrategy implements SearchStrategyInterface
{
    public function __construct(
        private readonly bool $negated = false,
    ) {
    }

    public function apply(QueryBuilder $qb, AbstractColumn $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        $field     = RelationFieldResolver::resolve($qb, $alias, $column->getField());
        $expr      = $qb->expr();
        $isNumeric = $column->isNumber() || \in_array(strtolower($search->type), ['number', 'numeric', 'num'], true);

        if ($isNumeric) {
            $qb->andWhere($this->negated ? $expr->isNotNull($field) : $expr->isNull($field));

            return;
        }

        $qb->andWhere($this->negated
            ? $expr->andX(
                $expr->isNotNull($field),
                $expr->neq($field, $expr->literal(''))
            )
            : $expr->orX(
                $expr->isNull($field),
                $expr->eq($field, $expr->literal(''))
            ));
    }

    public function getLogic(): string
    {
        return $this->negated ? 'notEmpty' : 'empty';
    }
}
