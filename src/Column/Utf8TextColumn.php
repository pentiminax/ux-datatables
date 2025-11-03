<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class Utf8TextColumn extends AbstractColumn
{
    public static function new(string $name): self
    {
        return static::createWithType($name, ColumnType::STRING_UTF8);
    }
}
