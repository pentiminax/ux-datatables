<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;

/**
 * Builds a DQL search condition for a column based on its type.
 *
 * For numeric columns: exact match when the value is numeric, null otherwise.
 * For native UUID/ULID columns: exact match when the value is a UUID or ULID, null otherwise.
 * For other columns: LIKE %value% when the field supports search filtering, null otherwise.
 *
 * A column is treated as numeric when {@see ColumnInterface::isNumber()} is true or when
 * the caller forces numeric handling via $forceNumeric (e.g. based on an external type hint).
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
        if ($column->isNumber() || $forceNumeric) {
            if (!is_numeric($value)) {
                return null;
            }

            return SearchConditionBuilder::numeric($qb, $alias, $field, $value, $paramName);
        }

        if ($column->isDate()) {
            return null;
        }

        if (RelationFieldResolver::supportsUuidEqualitySearch($qb, $field)) {
            if (!self::isUuidLike($value)) {
                return null;
            }

            return SearchConditionBuilder::numeric($qb, $alias, $field, $value, $paramName);
        }

        if (!RelationFieldResolver::supportsTextSearch($qb, $field)) {
            return null;
        }

        return SearchConditionBuilder::text($qb, $alias, $field, $value, $paramName);
    }

    private static function isUuidLike(string $value): bool
    {
        $value = trim($value);

        if (1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
            return true;
        }

        return 1 === preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $value);
    }
}
