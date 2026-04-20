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

    public function formatted(): static
    {
        return $this->setType(match ($this->type) {
            ColumnType::HTML_NUM, ColumnType::HTML_NUM_FMT => ColumnType::HTML_NUM_FMT,
            default                                        => ColumnType::NUM_FMT,
        });
    }

    public function html(): static
    {
        return $this->setType(match ($this->type) {
            ColumnType::NUM_FMT, ColumnType::HTML_NUM_FMT => ColumnType::HTML_NUM_FMT,
            default                                       => ColumnType::HTML_NUM,
        });
    }
}
