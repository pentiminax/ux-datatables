<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * Provider-neutral sort direction for an {@see OrderIntent}.
 */
enum SortDirection: string
{
    case Asc  = 'asc';
    case Desc = 'desc';

    /**
     * Normalize a raw DataTables direction string, defaulting to ascending.
     */
    public static function fromRequest(string $direction): self
    {
        return 'desc' === strtolower(trim($direction)) ? self::Desc : self::Asc;
    }
}
