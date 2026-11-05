<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

/**
 * @internal
 */
enum RowActionKind
{
    case Delete;
    case Toggle;
    case Detail;
    case EditFormView;
    case EditFormSubmit;

    public function mutates(): bool
    {
        return match ($this) {
            self::Delete, self::Toggle, self::EditFormSubmit => true,
            self::Detail, self::EditFormView                 => false,
        };
    }
}
