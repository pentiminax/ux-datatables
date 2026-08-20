<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Normalizes a search term before it is bound to a native date/time column.
 *
 * Doctrine's date/time types convert a DateTimeInterface value to the column's stored
 * representation; a raw string makes that conversion throw instead of simply not matching.
 * Callers skip the condition when normalization returns null, so an unparseable value
 * yields a graceful no-match rather than a 500.
 */
final class DateSearchTerm
{
    public static function normalize(string $value): ?\DateTimeImmutable
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
