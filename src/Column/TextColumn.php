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

    public function utf8(): static
    {
        return $this->setType(match ($this->type) {
            ColumnType::HTML, ColumnType::HTML_UTF8 => ColumnType::HTML_UTF8,
            default                                 => ColumnType::STRING_UTF8,
        });
    }

    public function html(): static
    {
        return $this->setType(match ($this->type) {
            ColumnType::STRING_UTF8, ColumnType::HTML_UTF8 => ColumnType::HTML_UTF8,
            default                                        => ColumnType::HTML,
        });
    }
}
