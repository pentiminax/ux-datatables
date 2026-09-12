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
     *
     * $value is escaped so a literal `%` or `_` in the search term is matched literally
     * rather than treated as a wildcard; the ESCAPE clause is what makes the database honor
     * that escaping — see {@see LikeValueEscaper}.
     *
     * Both sides are lowercased by default, because a bare LIKE is case-sensitive on
     * PostgreSQL and on binary MySQL collations. $caseSensitive keeps the raw column in the
     * condition so a prefix index stays usable — see
     * {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::setCaseSensitiveSearch()}.
     */
    public static function text(QueryBuilder $qb, string $alias, string $fieldPath, string $value, string $paramName, bool $caseSensitive = false): string
    {
        $field  = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $needle = $caseSensitive ? $value : mb_strtolower($value);

        $qb->setParameter($paramName, \sprintf('%%%s%%', LikeValueEscaper::escape($needle)));

        $expr = $caseSensitive ? $field : \sprintf('LOWER(%s)', $field);

        return \sprintf("%s LIKE :%s ESCAPE '%s'", $expr, $paramName, LikeValueEscaper::ESCAPE_CHARACTER);
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
