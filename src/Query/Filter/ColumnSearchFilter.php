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
 * Processes Column.search (standard DataTables API) with AND logic.
 * Delegates condition building to SearchPredicateFactory.
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

            $field = $column->getField();
            if (null === $field) {
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

            $paramName = \sprintf('column_search_param_%d', $index);
            $condition = SearchPredicateFactory::build($qb, $column, $context->alias, $field, $search->value, $paramName);

            if (null !== $condition) {
                $qb->andWhere($condition);
            }
        }
    }
}
