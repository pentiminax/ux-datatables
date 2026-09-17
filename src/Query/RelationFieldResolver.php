<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\Mapping\MappingException;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;

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
     * Native UUID/ULID Doctrine types. LIKE is invalid on PostgreSQL and SQL Server
     * (`uuid ~~ unknown`, UNIQUEIDENTIFIER), so these are searched with equality only.
     *
     * @var list<string>
     */
    private const array UUID_FIELD_TYPES = [
        'guid',
        'ulid',
        'uuid',
        'uuid_binary',
        'uuid_binary_ordered_time',
    ];

    /**
     * Doctrine date/time types. Comparison values must be converted to a DateTimeInterface
     * before binding: setParameter() throws when a date-typed parameter is given a raw string.
     *
     * @var list<string>
     */
    private const array DATE_FIELD_TYPES = [
        'date',
        'date_immutable',
        'datetime',
        'datetime_immutable',
        'datetimetz',
        'datetimetz_immutable',
    ];

    /**
     * Doctrine integer types. A non-numeric string bound without conversion makes PostgreSQL
     * reject the query (`invalid input syntax for type integer`) and makes MySQL silently
     * coerce the term to 0, matching the wrong rows.
     *
     * @var list<string>
     */
    private const array INTEGER_FIELD_TYPES = [
        'bigint',
        'integer',
        'smallint',
    ];

    /**
     * Doctrine floating-point / decimal types. Same bind contract as integers: the raw
     * search string must be numeric or the predicate is skipped.
     *
     * @var list<string>
     */
    private const array FLOAT_FIELD_TYPES = [
        'decimal',
        'float',
    ];

    /**
     * @var list<string>
     */
    private const array BOOLEAN_FIELD_TYPES = [
        'boolean',
    ];

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
        'decimal',
        'float',
        'integer',
        'json',
        'smallint',
        'time',
        'time_immutable',
        ...self::DATE_FIELD_TYPES,
        ...self::UUID_FIELD_TYPES,
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
        $existingAliases = self::getExistingJoinAliases($qb);
        $aliasesByJoin   = self::getExistingJoinAliasesByJoin($qb);

        foreach ($segments as $segment) {
            $join = \sprintf('%s.%s', $currentAlias, $segment);

            if (isset($aliasesByJoin[$join])) {
                $currentAlias = $aliasesByJoin[$join];

                continue;
            }

            $joinAlias = $currentAlias === $rootAlias ? $segment : \sprintf('%s_%s', $currentAlias, $segment);

            if (!isset($existingAliases[$joinAlias])) {
                $qb->leftJoin($join, $joinAlias);
                $existingAliases[$joinAlias] = true;
                $aliasesByJoin[$join]        = $joinAlias;
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
     *
     * A bare field that the root entity maps neither as a scalar nor as an association is
     * rejected too: it is a virtual column assembled in mapRow(), and emitting
     * "<alias>.<field>" for it makes Doctrine reject the whole query with "has no field or
     * association named ...", taking down the table rather than just that column. Such a
     * column is skipped instead, and its owner opts back into search with setSearchField(),
     * addSearchJoin(), or setSearchPredicate().
     */
    public static function supportsSearchFiltering(QueryBuilder $qb, ?string $fieldPath): bool
    {
        if (null === $fieldPath || '' === $fieldPath) {
            return false;
        }

        if (str_contains($fieldPath, '.')) {
            return true;
        }

        return self::isRootMappedField($qb, $fieldPath) && !self::isRootAssociationField($qb, $fieldPath);
    }

    /**
     * Apply the LEFT JOINs a column declared for its search predicate.
     *
     * Idempotent: a join whose alias is already on the QueryBuilder -- from
     * customizeQueryBuilder(), an earlier filter in the chain, or another column declaring the
     * same join -- is skipped, so every search filter can call this before resolving a field.
     *
     * A column that does not implement {@see SearchableColumnInterface} declares no joins, so
     * this is a no-op for it.
     */
    public static function applySearchJoins(QueryBuilder $qb, ColumnInterface $column): void
    {
        $joins = $column instanceof SearchableColumnInterface ? $column->getSearchJoins() : [];

        if ([] === $joins) {
            return;
        }

        $existingAliases = self::getExistingJoinAliases($qb);

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
     * The field path a column wants searched: its {@see SearchableColumnInterface::getSearchField()}
     * override when it declares one, otherwise the field it displays.
     *
     * Null when neither yields a path, which every search boundary reads as "skip this column".
     */
    public static function resolveSearchField(ColumnInterface $column): ?string
    {
        $searchField = $column instanceof SearchableColumnInterface ? $column->getSearchField() : null;
        $field       = $searchField ?? $column->getField();

        return null !== $field && '' !== $field ? $field : null;
    }

    /**
     * Returns whether a field path supports SQL LIKE text search.
     *
     * Non-string Doctrine field types (boolean, integer, datetime, uuid, etc.) are
     * rejected because operators like LIKE are not valid on those columns in strict
     * SQL engines such as PostgreSQL.
     */
    public static function supportsTextSearch(QueryBuilder $qb, string $fieldPath): bool
    {
        if (!self::supportsSearchFiltering($qb, $fieldPath)) {
            return false;
        }

        $fieldType = self::resolveFieldType($qb, $fieldPath);

        return null === $fieldType || self::isTextSearchableFieldType($fieldType);
    }

    /**
     * Returns the Doctrine type of a native UUID/ULID field path, or null when the field
     * is not one. Such fields can be compared with equality but must not be used with SQL LIKE.
     *
     * Callers need the type name itself, not only the answer, because the parameter must be
     * bound with it: `ulid` and the binary UUID types only match once Doctrine has converted
     * the search term to its stored representation.
     */
    public static function resolveUuidFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        return self::resolveListedFieldType($qb, $fieldPath, self::UUID_FIELD_TYPES);
    }

    /**
     * Returns the Doctrine type of a date/time field path, or null when the field is not one.
     *
     * Callers need the type name itself, not only the answer, because setParameter() must
     * bind with it: a DateTimeInterface value converts to the wrong column type otherwise.
     */
    public static function resolveDateFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        return self::resolveListedFieldType($qb, $fieldPath, self::DATE_FIELD_TYPES);
    }

    /**
     * Returns the Doctrine type of an integer field path, or null when the field is not one.
     */
    public static function resolveIntegerFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        return self::resolveListedFieldType($qb, $fieldPath, self::INTEGER_FIELD_TYPES);
    }

    /**
     * Returns the Doctrine type of a float/decimal field path, or null when the field is not one.
     */
    public static function resolveFloatFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        return self::resolveListedFieldType($qb, $fieldPath, self::FLOAT_FIELD_TYPES);
    }

    /**
     * Returns the Doctrine type of a boolean field path, or null when the field is not one.
     */
    public static function resolveBooleanFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        return self::resolveListedFieldType($qb, $fieldPath, self::BOOLEAN_FIELD_TYPES);
    }

    /**
     * @param list<string> $types
     */
    private static function resolveListedFieldType(QueryBuilder $qb, string $fieldPath, array $types): ?string
    {
        if (!self::supportsSearchFiltering($qb, $fieldPath)) {
            return null;
        }

        $fieldType = self::resolveFieldType($qb, $fieldPath);

        if (null === $fieldType || !\in_array($fieldType, $types, true)) {
            return null;
        }

        return $fieldType;
    }

    private static function isTextSearchableFieldType(string $type): bool
    {
        return !\in_array($type, self::NON_TEXT_SEARCHABLE_TYPES, true);
    }

    private static function resolveFieldType(QueryBuilder $qb, string $fieldPath): ?string
    {
        $metadata = self::rootMetadata($qb);

        if (null === $metadata) {
            return null;
        }

        $em       = $qb->getEntityManager();
        $segments = explode('.', $fieldPath);
        $field    = array_pop($segments);

        foreach ($segments as $segment) {
            if (!$metadata->hasAssociation($segment)) {
                return null;
            }

            $target = $metadata->getAssociationTargetClass($segment);

            try {
                $metadata = $em->getClassMetadata($target);
            } catch (MappingException) {
                return null;
            }
        }

        if (!$metadata->hasField($field)) {
            return null;
        }

        return $metadata->getFieldMapping($field)->type;
    }

    /**
     * Whether the root entity maps $fieldPath as a scalar field.
     *
     * Returns true when there is no root entity metadata to read, so a query builder the
     * bundle cannot introspect keeps every column searchable rather than silently losing
     * search on all of them.
     */
    private static function isRootMappedField(QueryBuilder $qb, string $fieldPath): bool
    {
        return self::rootMetadata($qb)?->hasField($fieldPath) ?? true;
    }

    private static function isRootAssociationField(QueryBuilder $qb, string $fieldPath): bool
    {
        return self::rootMetadata($qb)?->hasAssociation($fieldPath) ?? false;
    }

    /**
     * Metadata of the query builder's first root entity, or null when there is none to read.
     *
     * Null covers the two cases a search helper must survive rather than report: a query
     * builder with no root entity at all, and a root class Doctrine does not map. Every other
     * failure -- a mapping driver rejecting an attribute, a broken cache -- propagates, because
     * a search predicate is the wrong place to discover a misconfigured mapping and silencing
     * it here only moves the error to a DQL message that no longer names the cause.
     *
     * Callers pick their own default for null: {@see self::isRootMappedField()} permits the
     * field, {@see self::isRootAssociationField()} denies the association, and
     * {@see self::resolveFieldType()} reports an unknown type.
     */
    private static function rootMetadata(QueryBuilder $qb): ?ClassMetadata
    {
        $rootEntities = $qb->getRootEntities();

        if ([] === $rootEntities) {
            return null;
        }

        try {
            return $qb->getEntityManager()->getClassMetadata($rootEntities[0]);
        } catch (MappingException) {
            return null;
        }
    }

    /**
     * Aliases already registered on the QueryBuilder, keyed by the join expression they target
     * (e.g. 'e.donorProvider' => 'dp').
     *
     * {@see self::resolve()} consults this before deriving an alias of its own, so a relation
     * customizeQueryBuilder() or addSearchJoin() already joined under a chosen alias is reused
     * rather than joined a second time under the derived one.
     *
     * @return array<string, string>
     */
    private static function getExistingJoinAliasesByJoin(QueryBuilder $qb): array
    {
        $aliases = [];

        foreach ($qb->getDQLPart('join') as $joinParts) {
            foreach ($joinParts as $join) {
                $aliases[$join->getJoin()] = $join->getAlias();
            }
        }

        return $aliases;
    }

    /**
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
}
