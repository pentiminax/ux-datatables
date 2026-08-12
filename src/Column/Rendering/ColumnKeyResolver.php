<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column\Rendering;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;

/**
 * Resolves the two distinct keys a column carries: the key its value is stored under in a mapped
 * row, and the property path its value is read from on the source entity.
 */
final class ColumnKeyResolver
{
    /**
     * Key the column's value is stored under in a mapped row, or null when the column has none.
     */
    public static function rowKey(ColumnInterface $column): ?string
    {
        $key = $column->getData() ?? $column->getName();

        return null === $key || '' === $key ? null : $key;
    }

    /**
     * Property path the column's value is read from on the source entity. `getField()` falls back
     * to the column name, so an unset field must not shadow an explicitly configured row key.
     */
    public static function readPath(ColumnInterface $column, string $rowKey): string
    {
        $field = $column->getField();

        return (null !== $field && $field !== $column->getName()) ? $field : $rowKey;
    }
}
