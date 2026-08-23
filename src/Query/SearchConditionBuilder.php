<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Builds search conditions (LIKE / exact match) with field resolution and parameter binding.
 *
 * Shared by GlobalSearchFilter, ColumnSearchFilter, and ContainsSearchStrategy.
 */
final class SearchConditionBuilder
{
    /**
     * Build a case-insensitive LIKE %value% condition via UX_DATATABLES_SEARCH, set the
     * parameter, and return the DQL expression.
     *
     * The DQL function handles platform-specific casting (e.g. CAST AS TEXT on PostgreSQL)
     * and applies LOWER to the field. The bound value is lower-cased and escaped so a
     * literal `%` or `_` is matched literally rather than treated as a wildcard; the
     * ESCAPE clause inside {@see \Pentiminax\UX\DataTables\Doctrine\Function\UxDataTablesSearchFunction}
     * is what makes the database honor that escaping — see {@see LikeValueEscaper}.
     */
    public static function text(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName): string
    {
        $field = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $qb->setParameter($paramName, \sprintf('%%%s%%', LikeValueEscaper::escape(mb_strtolower($value))));

        return \sprintf('UX_DATATABLES_SEARCH(%s, :%s) = 1', $field, $paramName);
    }

    /**
     * Build an exact = condition, set the parameter, return the DQL expression.
     *
     * $doctrineType is the mapped type of the compared field. It is required for types whose
     * stored representation differs from the submitted string, such as `ulid` or the binary
     * UUID types: without it Doctrine binds the raw string and the comparison never matches.
     */
    public static function equality(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName, ?string $doctrineType = null): string
    {
        $field = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $qb->setParameter($paramName, $value, $doctrineType);

        return \sprintf('%s = :%s', $field, $paramName);
    }

    /**
     * Build an exact = condition for a numeric column.
     *
     * @see self::equality()
     */
    public static function numeric(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName): string
    {
        return self::equality($qb, $alias, $fieldPath, $value, $paramName);
    }
}
