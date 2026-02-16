<?php

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

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
            if ($column instanceof TextColumn) {
                $conditions[] = $this->applyTextSearch($qb, $column, $searchValue, $index, $context->alias);
            } elseif ($column->isNumber() && is_numeric($searchValue)) {
                $conditions[] = $this->applyNumericSearch($qb, $column, $searchValue, $index, $context->alias);
            }
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }

    private function applyTextSearch(QueryBuilder $qb, AbstractColumn $column, string $searchValue, int $index, string $alias): string
    {
        $paramName = \sprintf('search_param_%d', $index);
        $qb->setParameter($paramName, "%$searchValue%");

        return \sprintf('%s LIKE :%s', RelationFieldResolver::resolve($qb, $alias, $column->getField()), $paramName);
    }

    private function applyNumericSearch(QueryBuilder $qb, AbstractColumn $column, string $searchValue, int $index, string $alias): string
    {
        $paramName = \sprintf('search_param_%d', $index);
        $qb->setParameter($paramName, $searchValue);

        return \sprintf('%s = :%s', RelationFieldResolver::resolve($qb, $alias, $column->getField()), $paramName);
    }
}
