<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Exception;

/**
 * Raised when two actions share the same name on the same row.
 *
 * `Actions::add()` rejects duplicates within a single collection, but nothing prevents combining
 * two separate `ActionColumn::fromActions()` calls whose collections both declare the same name
 * (for example two `Action::delete()` actions). That collision silently overwrites the row's
 * resolved action data (url, CSRF token, id) and flattens the denied-actions list, so it is
 * rejected explicitly instead.
 */
final class DuplicateActionNameException extends \InvalidArgumentException
{
    public static function forName(string $name): self
    {
        return new self(\sprintf(
            'Action name "%s" is used by more than one action column on the same row. Rename one of the actions or merge the action columns.',
            $name,
        ));
    }
}
