<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class HtmlColumn extends AbstractColumn
{
    public static function new(string $name): self
    {
        return static::createWithType($name, ColumnType::HTML);
    }
}
