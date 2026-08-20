<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;

/**
 * Central helper that encapsulates two search-specific concerns:
 *
 * 1. Applying any column-declared search joins to the QueryBuilder (idempotent).
 * 2. Resolving the effective field path for search — using the column's
 *    dedicated {@see ColumnInterface::getSearchField()} when set, or falling
 *    back to {@see ColumnInterface::getField()} otherwise.
 *
 * All search filters and strategies delegate to this class so the resolution
 * logic lives in exactly one place. Row mapping, form mapping, ordering, and the
 * serialized client payload are unaffected.
 */
final class ColumnSearchResolver
{
    /**
     * Apply all search joins declared on the column to the QueryBuilder.
     *
     * Each join is idempotent: if the alias is already registered on the
     * QueryBuilder (either from customizeQueryBuilder() or a previous
     * applySearchJoins() call), the join is silently skipped.
     */
    public static function applySearchJoins(QueryBuilder $qb, ColumnInterface $column): void
    {
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
     * Returns {@see ColumnInterface::getSearchField()} when it has been set,
     * otherwise falls back to {@see ColumnInterface::getField()}.
     *
     * Returns null when neither method yields a non-null, non-empty value.
     */
    public static function resolveField(ColumnInterface $column): ?string
    {
        $field = $column->getSearchField() ?? $column->getField();

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
