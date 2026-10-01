<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

use Doctrine\Persistence\Mapping\ClassMetadata;
use Pentiminax\UX\DataTables\Highlight\HighlightConfig;

/**
 * The property a table writes as `DT_RowId`.
 *
 * The row mapper writes the row id from it and the bulk endpoint looks the selected rows up by it,
 * so both must ask here: a lookup in any other field compares values from another namespace and
 * mutates the wrong rows.
 *
 * @internal
 */
final class RowIdField
{
    /**
     * Highlight wins and is never remapped: it writes DT_RowId from its own field. Otherwise the
     * bulk id field is used, with the default `id` remapped onto a single Doctrine identifier.
     *
     * @param \Closure(): (ClassMetadata<object>|null) $metadata called only when the default `id` may need remapping
     */
    public static function resolve(?HighlightConfig $highlight, ?string $bulkIdField, \Closure $metadata): ?string
    {
        if (null !== $highlight) {
            return $highlight->idField;
        }

        if ('id' !== $bulkIdField) {
            return $bulkIdField;
        }

        $identifiers = $metadata()?->getIdentifier() ?? [];

        return 1 === \count($identifiers) ? $identifiers[0] : $bulkIdField;
    }
}
