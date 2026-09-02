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

        $segments      = explode('.', $fieldPath);
        $leafField     = array_pop($segments);
        $currentAlias  = $rootAlias;
        $existingJoins = self::getExistingJoinAliases($qb);

        foreach ($segments as $segment) {
            $joinAlias = $currentAlias === $rootAlias ? $segment : \sprintf('%s_%s', $currentAlias, $segment);

            if (!isset($existingJoins[$joinAlias])) {
                $qb->leftJoin(\sprintf('%s.%s', $currentAlias, $segment), $joinAlias);
                $existingJoins[$joinAlias] = true;
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
