<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;

/**
 * Builds a DQL search condition for a column based on its type.
 *
 * Resolution order:
 *
 *  1. If the column implements {@see SearchableColumnInterface} and its
 *     {@see SearchableColumnInterface::buildSearchPredicate()} returns a non-null
 *     DQL fragment, that fragment is returned verbatim. The caller is responsible
 *     for binding any parameters on the QueryBuilder inside the predicate.
 *
 *  2. Otherwise, the effective field path is resolved via
 *     {@see ColumnSearchResolver::resolveField()} (respecting
 *     {@see ColumnInterface::getSearchField()} before falling back to
 *     {@see ColumnInterface::getField()}), and the standard type-based predicate
 *     is built:
 *       - Numeric columns: exact match when the value is numeric, null otherwise.
 *       - Date columns: always null (unsupported by default text search).
 *       - Other columns: LIKE %value% when the field supports text search.
 *
 * A column is treated as numeric when {@see ColumnInterface::isNumber()} is true or
 * when the caller forces numeric handling via $forceNumeric.
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
        bool $forceNumeric = false,
    ): ?string {
        // Layer 2: custom predicate takes full precedence.
        if ($column instanceof SearchableColumnInterface) {
            $predicate = $column->buildSearchPredicate($qb, $alias, $value, $paramName);
            if (null !== $predicate) {
                return $predicate;
            }
        }

        // Layer 1 / default: field-based predicate.
        if ($column->isNumber() || $forceNumeric) {
            if (!is_numeric($value)) {
                return null;
            }

            return SearchConditionBuilder::numeric($qb, $alias, $field, $value, $paramName);
        }

        if ($column->isDate()) {
            return null;
        }

        if (!RelationFieldResolver::supportsTextSearch($qb, $field)) {
            return null;
        }

        return SearchConditionBuilder::text($qb, $alias, $field, $value, $paramName);
    }
}
