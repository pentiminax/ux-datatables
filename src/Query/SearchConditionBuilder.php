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
     * Build a LIKE %value% condition, set the parameter, return the DQL expression.
     */
    public static function text(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName): string
    {
        $field = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $qb->setParameter($paramName, \sprintf('%%%s%%', $value));

        return \sprintf('%s LIKE :%s', $field, $paramName);
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
