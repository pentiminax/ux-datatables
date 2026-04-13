<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;

/**
 * Strategy for 'contains' search logic.
 *
 * Performs a case-sensitive substring search using SQL LIKE %value%.
 * For numeric columns, performs exact match if the value is numeric.
 */
final class ContainsSearchStrategy implements SearchStrategyInterface
{
    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        $paramName = \sprintf('column_control_param_%d', $paramIndex);

        $isNumeric = $column->isNumber()
                     || \in_array(strtolower($search->type), ['number', 'numeric', 'num'], true);

        if ($isNumeric) {
            if (!is_numeric($search->value)) {
                return;
            }
            $qb->andWhere(SearchConditionBuilder::numeric($qb, $alias, $column->getField(), $search->value, $paramName));
        } else {
            $qb->andWhere(SearchConditionBuilder::text($qb, $alias, $column->getField(), $search->value, $paramName));
        }
    }

    public function getLogic(): string
    {
        return 'contains';
    }
}
