<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Resolves dot-notation field paths into valid DQL expressions.
 *
 * For simple fields (no dots), returns "$alias.$field" unchanged.
 * For relation paths like "author.firstName", adds LEFT JOIN clauses
 * to the QueryBuilder and returns the resolved DQL expression.
 */
final class RelationFieldResolver
{
    /**
     * Doctrine field types that cannot be used with SQL LIKE without an explicit cast.
     *
     * @var list<string>
     */
    private const array NON_TEXT_SEARCHABLE_TYPES = [
        'bigint',
        'binary',
        'blob',
        'boolean',
        'date',
        'date_immutable',
        'datetime',
        'datetime_immutable',
        'datetimetz',
        'datetimetz_immutable',
        'decimal',
        'float',
        'integer',
        'json',
        'smallint',
        'time',
        'time_immutable',
    ];

    /**
     * Resolve a field path into a DQL expression, adding LEFT JOINs as needed.
     *
     * Examples:
     *   resolve($qb, 'e', 'name')                → 'e.name'
     *   resolve($qb, 'e', 'author.firstName')     → 'author.firstName'  (joins e.author as author)
     *   resolve($qb, 'e', 'author.address.city')  → 'author_address.city' (joins e.author, author.address)
     */
    public static function resolve(QueryBuilder $qb, string $rootAlias, string $fieldPath): string
    {
        if (!str_contains($fieldPath, '.')) {
            return \sprintf('%s.%s', $rootAlias, $fieldPath);
        }

        $segments        = explode('.', $fieldPath);
        $leafField       = array_pop($segments);
        $currentAlias    = $rootAlias;
        $existingByAlias = self::getExistingJoinAliases($qb);
        $existingByJoin  = self::getExistingJoinsByExpression($qb);

        foreach ($segments as $segment) {
            $joinExpression = \sprintf('%s.%s', $currentAlias, $segment);

            // Prefer an existing join that targets the same expression, even when
            // it was added under a user-chosen alias (e.g. 'dp' for 'e.donorProvider').
            if (isset($existingByJoin[$joinExpression])) {
                $currentAlias = $existingByJoin[$joinExpression];
                continue;
            }

            $joinAlias = $currentAlias === $rootAlias ? $segment : \sprintf('%s_%s', $currentAlias, $segment);

            if (!isset($existingByAlias[$joinAlias])) {
                $qb->leftJoin($joinExpression, $joinAlias);
                $existingByAlias[$joinAlias]     = true;
                $existingByJoin[$joinExpression] = $joinAlias;
            }

            $currentAlias = $joinAlias;
        }

        return \sprintf('%s.%s', $currentAlias, $leafField);
    }

    /**
     * Returns whether a field path can be used for search/filter conditions.
     *
     * Bare association fields such as "client" are rejected because they do not
     * resolve to a scalar column. Explicit scalar paths such as "client.name"
     * remain supported through join resolution.
     */
    public static function supportsSearchFiltering(QueryBuilder $qb, ?string $fieldPath): bool
    {
        if (null === $fieldPath || '' === $fieldPath) {
            return false;
        }

        if (str_contains($fieldPath, '.')) {
            return true;
        }

        return !self::isRootAssociationField($qb, $fieldPath);
    }

    /**
     * Returns whether a field path supports SQL LIKE text search.
     *
     * Non-string Doctrine field types (boolean, integer, datetime, etc.) are rejected
     * because operators like LIKE are not valid on those columns in strict SQL engines
     * such as PostgreSQL.
     */
    public static function supportsTextSearch(QueryBuilder $qb, string $fieldPath): bool
    {
        if (!self::supportsSearchFiltering($qb, $fieldPath)) {
            return false;
        }

        $fieldType = self::resolveFieldType($qb, $fieldPath);

        return null === $fieldType || self::isTextSearchableFieldType($fieldType);
    }

    private static function isTextSearchableFieldType(string $type): bool
    {
        return !\in_array($type, self::NON_TEXT_SEARCHABLE_TYPES, true);
    }

    private static function resolveFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        try {
            $rootEntities = $qb->getRootEntities();
            if ([] === $rootEntities) {
                return null;
            }

            $em       = $qb->getEntityManager();
            $metadata = $em->getClassMetadata($rootEntities[0]);
            $segments = explode('.', $fieldPath);
            $field    = array_pop($segments);

            foreach ($segments as $segment) {
                if (!$metadata->hasAssociation($segment)) {
                    return null;
                }

                $metadata = $em->getClassMetadata($metadata->getAssociationTargetClass($segment));
            }

            if (!$metadata->hasField($field)) {
                return null;
            }

            return $metadata->getFieldMapping($field)->type;
        } catch (\Throwable) {
            return null;
        }
    }

    private static function isRootAssociationField(QueryBuilder $qb, string $fieldPath): bool
    {
        try {
            $rootEntities = $qb->getRootEntities();
            if (empty($rootEntities)) {
                return false;
            }

            return $qb->getEntityManager()
                ->getClassMetadata($rootEntities[0])
                ->hasAssociation($fieldPath);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Return a map of existing join aliases and their join expressions.
     *
     * Keys are join aliases (e.g. 'dp'). Values are always true, but the map is
     * supplemented by {@see self::getExistingJoinsByExpression()} when resolving
     * relation paths, so that a join already present under a different alias is
     * reused rather than duplicated.
     *
     * @return array<string, true>
     */
    private static function getExistingJoinAliases(QueryBuilder $qb): array
    {
        $aliases = [];

        foreach ($qb->getDQLPart('join') as $joinParts) {
            foreach ($joinParts as $join) {
                $aliases[$join->getAlias()] = true;
            }
        }

        return $aliases;
    }

    /**
     * Return a map from join expression (e.g. 'e.donorProvider') to its alias (e.g. 'dp').
     *
     * Used by {@see self::resolve()} to reuse an existing join that targets the
     * same association even when it was added under a user-chosen alias in
     * customizeQueryBuilder() instead of the auto-generated one.
     *
     * @return array<string, string>
     */
    private static function getExistingJoinsByExpression(QueryBuilder $qb): array
    {
        $byExpression = [];

        foreach ($qb->getDQLPart('join') as $joinParts) {
            foreach ($joinParts as $join) {
                $byExpression[$join->getJoin()] = $join->getAlias();
            }
        }

        return $byExpression;
    }
}
