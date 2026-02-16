<?php

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Filter that applies standard DataTables column-specific searches.
 *
 * Processes Column.search (standard DataTables API) with AND logic.
 * For text columns: performs LIKE %value% search.
 * For numeric columns: performs exact match if value is numeric.
 *
 * Distinct from ColumnControlSearchFilter which handles custom column control searches.
 */
final class ColumnSearchFilter implements QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        foreach ($context->columns as $index => $column) {
            if (!$column->isSearchable()) {
                continue;
            }

            $requestColumn = $context->request->columns->getColumnByIndex($index);
            if (!$requestColumn) {
                continue;
            }

            $search = $requestColumn->search;
            if (!$search || null === $search->value || '' === trim($search->value)) {
                continue;
            }

            if ($column->isNumber()) {
                $this->applyNumericColumnSearch($qb, $column, $search->value, $index, $context->alias);
            } else {
                $this->applyTextColumnSearch($qb, $column, $search->value, $index, $context->alias);
            }
        }
    }

    private function applyTextColumnSearch(
        QueryBuilder $qb,
        AbstractColumn $column,
        string $searchValue,
        int $index,
        string $alias,
    ): void {
        $field     = RelationFieldResolver::resolve($qb, $alias, $column->getField());
        $paramName = \sprintf('column_search_param_%d', $index);

        $qb->andWhere(\sprintf('%s LIKE :%s', $field, $paramName));
        $qb->setParameter($paramName, \sprintf('%%%s%%', $searchValue));
    }

    private function applyNumericColumnSearch(
        QueryBuilder $qb,
        AbstractColumn $column,
        string $searchValue,
        int $index,
        string $alias,
    ): void {
        if (!is_numeric($searchValue)) {
            return;
        }

        $field     = RelationFieldResolver::resolve($qb, $alias, $column->getField());
        $paramName = \sprintf('column_search_param_%d', $index);

        $qb->andWhere(\sprintf('%s = :%s', $field, $paramName));
        $qb->setParameter($paramName, $searchValue);
    }
}
