<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class NumberColumn extends AbstractColumn
{
    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::NUM);
    }

    /**
     * Create a number column with locale-aware formatting (ColumnType::NUM_FMT).
     */
    public static function formatted(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::NUM_FMT);
    }

    /**
     * Create an HTML-aware number column (ColumnType::HTML_NUM).
     */
    public static function html(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML_NUM);
    }

    /**
     * Create an HTML-aware number column with locale-aware formatting (ColumnType::HTML_NUM_FMT).
     */
    public static function htmlFormatted(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML_NUM_FMT);
    }
}
