<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Normalizes a search term before it is bound to a native UUID/ULID column.
 *
 * Malformed terms must never reach setParameter() with an identifier Doctrine type:
 * conversion throws instead of simply not matching, and PostgreSQL rejects the literal
 * with SQLSTATE 22P02. Callers skip the condition when normalization returns null.
 */
final class UuidSearchTerm
{
    public static function normalize(string $value): ?string
    {
        $identifier = trim($value);

        if (1 === preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            return $identifier;
        }

        return 1 === preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/i', $identifier) ? $identifier : null;
    }
}
