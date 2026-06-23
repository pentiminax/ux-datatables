<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;

/**
 * Filter that applies standard DataTables column-specific searches.
 *
 * Consumes the normalized {@see \Pentiminax\UX\DataTables\Query\Intent\ColumnSearchIntent}
 * criteria with AND logic. Delegates condition building to SearchPredicateFactory.
 *
 * Distinct from ColumnControlSearchFilter which handles custom column control searches.
 */
final class ColumnSearchFilter implements QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        foreach ($context->intent->columnSearches as $columnSearch) {
            $reference = $columnSearch->column;

            $column = $context->columnByName($reference->name);
            $field  = $reference->fieldPath;

            if (null === $column || null === $field) {
                continue;
            }

            $paramName = \sprintf('column_search_param_%d', $context->paramIndexFor($reference));
            $condition = SearchPredicateFactory::build($qb, $column, $context->alias, $field, $columnSearch->value, $paramName);

            if (null !== $condition) {
                $qb->andWhere($condition);
            }
        }
    }
}
