<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\RowMapper;

/**
 * Pairs the original source item hydrated by the data source with the projected
 * item produced by a page projector.
 *
 * Columns and Twig read displayed values from {@see self::$item}; actions, URLs,
 * and permission checks receive {@see self::$source}. When no projector is active,
 * both reference the same value.
 */
final readonly class RowContext
{
    public function __construct(
        public mixed $source,
        public mixed $item,
    ) {
    }
}
