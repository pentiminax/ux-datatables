<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Normalizes a search term before it is bound to a numeric Doctrine column.
 *
 * PostgreSQL rejects a non-numeric literal on integer/float columns (`invalid input syntax
 * for type integer`). MySQL silently coerces the literal to 0, so an equals search for
 * "abc" matches every row whose value is 0. Callers skip the condition when normalization
 * returns null, matching {@see DateSearchTerm} and {@see UuidSearchTerm}.
 */
final class NumericSearchTerm
{
    /**
     * @var list<string>
     */
    private const array INTEGER_TYPES = ['bigint', 'integer', 'smallint'];

    public static function normalize(string $value, string $doctrineType): ?string
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        if (\in_array($doctrineType, self::INTEGER_TYPES, true)) {
            return 1 === preg_match('/^-?\d+$/', $value) ? $value : null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        if (!is_finite((float) $value)) {
            return null;
        }

        return $value;
    }
}
