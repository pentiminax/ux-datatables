<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class TextColumn extends AbstractColumn
{
    public static function new(string $name, string $title = ''): self
    {
        return static::createWithType($name, $title, ColumnType::STRING);
    }
}
