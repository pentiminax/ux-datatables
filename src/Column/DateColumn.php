<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class DateColumn extends AbstractColumn
{
    public const string DEFAULT_DATE_FORMAT = 'Y-m-d';
    public const string OPTION_DATE_FORMAT  = 'dateFormat';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::DATE);
    }

    public function setFormat(?string $format): self
    {
        if (null === $format) {
            unset($this->customOptions[self::OPTION_DATE_FORMAT]);

            return $this;
        }

        $this->setCustomOption(self::OPTION_DATE_FORMAT, $format);

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->getCustomOption(self::OPTION_DATE_FORMAT) ?? self::DEFAULT_DATE_FORMAT;
    }
}
