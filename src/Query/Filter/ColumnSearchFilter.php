<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;

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

            $paramName = \sprintf('column_search_param_%d', $index);

            if ($column->isNumber()) {
                if (!is_numeric($search->value)) {
                    continue;
                }
                $qb->andWhere(SearchConditionBuilder::numeric($qb, $context->alias, $column->getField(), $search->value, $paramName));
            } else {
                if (RelationFieldResolver::isAssociationField($qb, $column->getField())) {
                    continue;
                }
                $qb->andWhere(SearchConditionBuilder::text($qb, $context->alias, $column->getField(), $search->value, $paramName));
            }
        }
    }
}
