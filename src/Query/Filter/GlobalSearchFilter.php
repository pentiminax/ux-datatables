<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;

/**
 * Filter that applies global search across all globally searchable columns.
 *
 * Reads the normalized {@see \Pentiminax\UX\DataTables\Query\Intent\GlobalSearchIntent}
 * and the globally searchable column references from the intent. Delegates condition
 * building to SearchPredicateFactory. All conditions are combined with OR logic.
 */
final class GlobalSearchFilter implements QueryFilterInterface
{
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

            $paramName = \sprintf('search_param_%d', $context->paramIndexFor($reference));
            $condition = SearchPredicateFactory::build($qb, $column, $context->alias, $field, $globalSearch->value, $paramName);

            if (null !== $condition) {
                $conditions[] = $condition;
            }
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }
}
