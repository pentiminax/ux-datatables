<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;

/**
 * Builds a DQL search condition for a column based on its type.
 *
 * For numeric columns: exact match when the value is numeric, null otherwise.
 * For other columns: LIKE %value% when the field supports search filtering, null otherwise.
 */
final class SearchPredicateFactory
{
    public static function build(
        QueryBuilder $qb,
        ColumnInterface $column,
        string $alias,
        string $field,
        string $value,
        string $paramName,
    ): ?string {
        if ($column->isNumber()) {
            if (!is_numeric($value)) {
                return null;
            }

            return SearchConditionBuilder::numeric($qb, $alias, $field, $value, $paramName);
        }

        if (!RelationFieldResolver::supportsSearchFiltering($qb, $field)) {
            return null;
        }

        return SearchConditionBuilder::text($qb, $alias, $field, $value, $paramName);
    }
}
