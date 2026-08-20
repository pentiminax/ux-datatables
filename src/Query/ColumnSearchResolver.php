<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchAwareColumnInterface;

/**
 * Central helper that encapsulates two search-specific concerns:
 *
 * 1. Applying any column-declared search joins to the QueryBuilder (idempotent).
 * 2. Resolving the effective field path for search — using the column's
 *    dedicated {@see SearchAwareColumnInterface::getSearchField()} when set, or
 *    falling back to {@see ColumnInterface::getField()} otherwise.
 *
 * All search filters and strategies delegate to this class so the resolution
 * logic lives in exactly one place. Row mapping, form mapping, ordering, and the
 * serialized client payload are unaffected.
 *
 * Columns that implement only {@see ColumnInterface} (without
 * {@see SearchAwareColumnInterface}) are supported: applySearchJoins() is a
 * no-op for them and resolveField() falls back to getField().
 */
final class ColumnSearchResolver
{
    /**
     * Apply all search joins declared on the column to the QueryBuilder.
     *
     * Each join is idempotent: if the alias is already registered on the
     * QueryBuilder (either from customizeQueryBuilder() or a previous
     * applySearchJoins() call), the join is silently skipped.
     *
     * Columns that do not implement {@see SearchAwareColumnInterface} are skipped.
     */
    public static function applySearchJoins(QueryBuilder $qb, ColumnInterface $column): void
    {
        if (!$column instanceof SearchAwareColumnInterface) {
            return;
        }

        $joins = $column->getSearchJoins();
        if ([] === $joins) {
            return;
        }

        $existingAliases = self::existingJoinAliases($qb);

        foreach ($joins as $join) {
            if (isset($existingAliases[$join['alias']])) {
                continue;
            }

            if (null !== $join['conditionType'] && null !== $join['condition']) {
                $qb->leftJoin($join['join'], $join['alias'], $join['conditionType'], $join['condition']);
            } else {
                $qb->leftJoin($join['join'], $join['alias']);
            }

            $existingAliases[$join['alias']] = true;
        }
    }

    /**
     * Resolve the effective field path for a search predicate.
     *
     * Returns {@see SearchAwareColumnInterface::getSearchField()} when the column
     * implements {@see SearchAwareColumnInterface} and the value is non-null,
     * otherwise falls back to {@see ColumnInterface::getField()}.
     *
     * Returns null when neither yields a non-null, non-empty value.
     */
    public static function resolveField(ColumnInterface $column): ?string
    {
        $searchField = $column instanceof SearchAwareColumnInterface
            ? $column->getSearchField()
            : null;

        $field = $searchField ?? $column->getField();

        return (null !== $field && '' !== $field) ? $field : null;
    }

    /**
     * @return array<string, true>
     */
    private static function existingJoinAliases(QueryBuilder $qb): array
    {
        $aliases = [];

        foreach ($qb->getDQLPart('join') as $joinParts) {
            foreach ($joinParts as $join) {
                $aliases[$join->getAlias()] = true;
            }
        }

        return $aliases;
    }
}
