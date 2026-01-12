<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class DateColumn extends AbstractColumn
{
    public const OPTION_DATE_FORMAT = 'dateFormat';

    public static function new(string $name, string $title = ''): self
    {
        return static::createWithType($name, $title, ColumnType::DATE);
    }

    public function setFormat(?string $format): self
    {
        $this->setCustomOption(self::OPTION_DATE_FORMAT, $format);

        return $this;
    }

    public function getFormat()
    {
        return $this->getCustomOption(self::OPTION_DATE_FORMAT);
    }
}
