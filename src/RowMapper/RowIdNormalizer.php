<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

/**
 * Row identifiers travel to the browser as strings: a JSON number loses precision above
 * Number.MAX_SAFE_INTEGER and drops the leading zeros of a padded key.
 *
 * @internal
 */
final class RowIdNormalizer
{
    public static function normalize(mixed $id): ?string
    {
        if (\is_int($id)) {
            return (string) $id;
        }

        if (\is_string($id) || $id instanceof \Stringable) {
            $id = (string) $id;

            return '' !== $id ? $id : null;
        }

        return null;
    }
}
