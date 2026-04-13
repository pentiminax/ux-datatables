<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class TextColumn extends AbstractColumn
{
    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::STRING);
    }

    /**
     * Create a UTF-8 aware text column (ColumnType::STRING_UTF8).
     */
    public static function utf8(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::STRING_UTF8);
    }

    /**
     * Create an HTML-aware text column (ColumnType::HTML).
     */
    public static function html(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    /**
     * Create an HTML + UTF-8 aware text column (ColumnType::HTML_UTF8).
     */
    public static function htmlUtf8(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML_UTF8);
    }
}
