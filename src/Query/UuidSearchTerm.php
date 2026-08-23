<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Normalizes a search term before it is bound to a native UUID/ULID column.
 *
 * Malformed terms must never reach setParameter() with an identifier Doctrine type:
 * conversion throws instead of simply not matching, and PostgreSQL rejects the literal
 * with SQLSTATE 22P02. Callers skip the condition when normalization returns null.
 *
 * When $doctrineType is provided, a valid identifier of the other family is also rejected:
 * a ULID bound as `guid`/`uuid` (or a UUID bound as `ulid`) makes Doctrine conversion throw.
 */
final class UuidSearchTerm
{
    public static function normalize(string $value, ?string $doctrineType = null): ?string
    {
        $identifier = trim($value);

        if (self::isUuid($identifier)) {
            return 'ulid' === $doctrineType ? null : $identifier;
        }

        if (self::isUlid($identifier)) {
            return null === $doctrineType || 'ulid' === $doctrineType ? $identifier : null;
        }

        return null;
    }

    private static function isUuid(string $identifier): bool
    {
        return 1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier);
    }

    private static function isUlid(string $identifier): bool
    {
        return 1 === preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $identifier);
    }
}
