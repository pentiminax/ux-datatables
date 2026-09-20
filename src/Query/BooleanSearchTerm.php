<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

/**
 * Normalizes a search term before it is bound to a boolean Doctrine column.
 *
 * A raw string such as "yes" is not a portable boolean literal: PostgreSQL rejects it
 * (`invalid input syntax for type boolean`) and MySQL coerces it to 0. Callers skip the
 * condition when normalization returns null.
 */
final class BooleanSearchTerm
{
    public static function normalize(string $value): ?bool
    {
        $value = trim($value);

        if ('' === $value) {
            return null;
        }

        $normalized = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);

        return \is_bool($normalized) ? $normalized : null;
    }
}
