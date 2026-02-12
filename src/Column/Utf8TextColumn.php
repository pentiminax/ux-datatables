<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class Utf8TextColumn extends AbstractColumn
{
    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::STRING_UTF8);
    }
}
