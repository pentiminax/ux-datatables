<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * @deprecated since 0.x, use {@see TextColumn::htmlUtf8()} instead. Will be removed in 1.0.
 */
class HtmlUtf8Column extends AbstractColumn
{
    public static function new(string $name, string $title = ''): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '0.x', 'The "%s" class is deprecated, use "%s::htmlUtf8()" instead.', self::class, TextColumn::class);

        return static::createWithType($name, $title, ColumnType::HTML_UTF8);
    }
}
