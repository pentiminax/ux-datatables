<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Normalizes a search term before it is bound to a numeric Doctrine column.
 *
 * PostgreSQL rejects a non-numeric literal on integer/float columns (`invalid input syntax
 * for type integer`). MySQL silently coerces the literal to 0, so an equals search for
 * "abc" matches every row whose value is 0. A digit-only literal wider than the mapped
 * integer type is rejected for the same reason: it reaches the database as an out-of-range
 * value instead of matching nothing. Callers skip the condition when normalization returns
 * null, matching {@see DateSearchTerm} and {@see UuidSearchTerm}.
 */
final class NumericSearchTerm
{
    /**
     * Widest magnitude each Doctrine integer type stores, as an absolute decimal string:
     * [negative bound, positive bound]. The keys are the supported integer types.
     *
     * @var array<string, array{string, string}>
     */
    private const array INTEGER_MAGNITUDES = [
        'bigint'   => ['9223372036854775808', '9223372036854775807'],
        'integer'  => ['2147483648', '2147483647'],
        'smallint' => ['32768', '32767'],
    ];

    public static function normalize(string $value, string $doctrineType): ?string
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        $magnitudes = self::INTEGER_MAGNITUDES[$doctrineType] ?? null;

        if (null !== $magnitudes) {
            return self::normalizeInteger($value, $magnitudes);
        }

        return self::normalizeFloat($value);
    }

    /**
     * @param array{string, string} $magnitudes
     */
    private static function normalizeInteger(string $value, array $magnitudes): ?string
    {
        if (1 !== preg_match('/^(?<sign>[+-]?)(?<digits>\d+)$/', $value, $matches)) {
            return null;
        }

        $digits = ltrim($matches['digits'], '0');

        if ('' === $digits) {
            return '0';
        }

        $negative = '-' === $matches['sign'];

        if (self::exceeds($digits, $magnitudes[$negative ? 0 : 1])) {
            return null;
        }

        return $negative ? '-'.$digits : $digits;
    }

    private static function normalizeFloat(string $value): ?string
    {
        if (!is_numeric($value)) {
            return null;
        }

        if (!is_finite((float) $value)) {
            return null;
        }

        return ltrim($value, '+');
    }

    /**
     * Compares two zero-stripped decimal magnitudes without casting them to int, which
     * would clamp anything wider than PHP_INT_MAX and hide the overflow we look for.
     */
    private static function exceeds(string $digits, string $limit): bool
    {
        if (\strlen($digits) !== \strlen($limit)) {
            return \strlen($digits) > \strlen($limit);
        }

        return strcmp($digits, $limit) > 0;
    }
}
