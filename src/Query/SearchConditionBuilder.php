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
     * and applies LOWER to the field, making all text searches case-insensitive.
     * The bound parameter value is also lower-cased here so both sides match.
     */
    public static function text(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName): string
    {
        $field = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $qb->setParameter($paramName, \sprintf('%%%s%%', mb_strtolower($value)));

        return \sprintf('UX_DATATABLES_SEARCH(%s, :%s) = 1', $field, $paramName);
    }

    /**
     * Build an exact = condition, set the parameter, return the DQL expression.
     */
    public static function numeric(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName): string
    {
        $field = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $qb->setParameter($paramName, $value);

        return \sprintf('%s = :%s', $field, $paramName);
    }
}
