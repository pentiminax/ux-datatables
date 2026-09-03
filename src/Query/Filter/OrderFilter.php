<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Filter that applies ordering from the normalized query intent to the QueryBuilder.
 *
 * Consumes {@see \Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent::$orderColumn}
 * and {@see \Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent::$orderDir}. The
 * raw Doctrine order expression
 * ({@see \Pentiminax\UX\DataTables\Contracts\ColumnInterface::getOrderExpression()})
 * stays out of the intent and is resolved here by column name.
 *
 * A virtual column assembled in mapRow() has no mapped field. Emitting
 * "<alias>.<field>" for it makes Doctrine reject the whole query -- the same
 * failure search already skips. A declared order expression still wins, because
 * that is the documented opt-in for computed columns backed by a HIDDEN alias.
 */
final class OrderFilter implements QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $orderColumn = $context->intent->orderColumn;
        $orderDir    = $context->intent->orderDir;
        if (null === $orderColumn || null === $orderDir) {
            return;
        }

        $column = $context->columnByName($orderColumn->name);
        if (null === $column) {
            return;
        }

        $orderExpression = $column->getOrderExpression();
        if (null !== $orderExpression) {
            $qb->addOrderBy($orderExpression, $orderDir);

            return;
        }

        $field = $column->getField();
        if (null === $field || !RelationFieldResolver::supportsSearchFiltering($qb, $field)) {
            return;
        }

        $qb->addOrderBy(RelationFieldResolver::resolve($qb, $context->alias, $field), $orderDir);
    }
}
