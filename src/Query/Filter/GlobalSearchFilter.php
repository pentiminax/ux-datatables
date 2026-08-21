<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;

/**
 * Filter that applies global search across all globally searchable columns.
 *
 * Reads the normalized {@see \Pentiminax\UX\DataTables\Query\Intent\GlobalSearchIntent}
 * and the globally searchable column references from the intent. Delegates condition
 * building to the injected SearchPredicateBuilderInterface, so overriding
 * AbstractDataTable::createSearchPredicateBuilder() customizes this search. All conditions
 * are combined with OR logic: each column's condition must stay a returned DQL fragment
 * rather than a QueryBuilder::andWhere() call, since only this filter knows the columns
 * need to be OR'd together rather than required individually.
 */
final class GlobalSearchFilter implements QueryFilterInterface
{
    public function __construct(
        private readonly SearchPredicateBuilderInterface $predicateBuilder = new DefaultSearchPredicateBuilder(),
    ) {
    }

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $globalSearch = $context->intent->globalSearch;
        if (null === $globalSearch) {
            return;
        }

        $conditions = [];

        foreach ($context->intent->columns as $reference) {
            if (!$reference->globalSearchable) {
                continue;
            }

            $column = $context->columnByName($reference->name);
            $field  = $reference->fieldPath;

            if (null === $column || null === $field) {
                continue;
            }

            $paramName = \sprintf('search_param_%d', $context->nextParamIndex());
            $condition = $this->predicateBuilder->build($qb, $column, $context->alias, $field, $globalSearch->value, $paramName);

            if (null !== $condition) {
                $conditions[] = $condition;
            }
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }
}
