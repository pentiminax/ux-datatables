<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;

/**
 * Filter that applies global search across all globally searchable columns.
 *
 * Delegates condition building to SearchPredicateFactory.
 * All conditions are combined with OR logic.
 */
final class GlobalSearchFilter implements QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $globalSearchableColumns = array_filter(
            $context->columns,
            static fn (ColumnInterface $column) => $column->isGlobalSearchable()
        );

        if ([] === $globalSearchableColumns) {
            return;
        }

        $searchValue = $context->request->search->value ?? '';
        if ('' === trim($searchValue)) {
            return;
        }

        $conditions = [];

        foreach ($globalSearchableColumns as $index => $column) {
            $field = $column->getField();
            if (null === $field) {
                continue;
            }

            $paramName = \sprintf('search_param_%d', $index);
            $condition = SearchPredicateFactory::build($qb, $column, $context->alias, $field, $searchValue, $paramName);

            if (null !== $condition) {
                $conditions[] = $condition;
            }
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }
}
