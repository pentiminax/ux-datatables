<?php

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Filter that applies ordering from DataTableRequest to QueryBuilder.
 *
 * Currently only supports single-column ordering. Multi-column ordering
 * is ignored to maintain consistency with the original implementation.
 */
final class OrderFilter implements QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        if (1 !== \count($context->request->order)) {
            return;
        }

        $order  = $context->request->order[0];
        $column = $context->columns[$order->column] ?? null;

        if (!$column) {
            return;
        }

        $qb->addOrderBy(
            RelationFieldResolver::resolve($qb, $context->alias, $column->getField()),
            $order->dir
        );
    }
}
