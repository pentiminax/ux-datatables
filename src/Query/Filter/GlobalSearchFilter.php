<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;

/**
 * Filter that applies global search across all searchable columns.
 *
 * For text columns, performs LIKE %value% search.
 * For numeric columns, performs exact match if the search value is numeric.
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
            $paramName = \sprintf('search_param_%d', $index);

            if ($column instanceof TextColumn) {
                $conditions[] = SearchConditionBuilder::text($qb, $context->alias, $column->getField(), $searchValue, $paramName);
            } elseif ($column->isNumber() && is_numeric($searchValue)) {
                $conditions[] = SearchConditionBuilder::numeric($qb, $context->alias, $column->getField(), $searchValue, $paramName);
            }
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }
}
