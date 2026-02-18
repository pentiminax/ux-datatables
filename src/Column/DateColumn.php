<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class DateColumn extends AbstractColumn
{
    public const string OPTION_DATE_FORMAT = 'dateFormat';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::DATE);
    }

    public function setFormat(?string $format): self
    {
        $this->setCustomOption(self::OPTION_DATE_FORMAT, $format);

        return $this;
    }

    public function getFormat(): ?string
    {
        return $this->getCustomOption(self::OPTION_DATE_FORMAT);
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            self::OPTION_DATE_FORMAT => $this->getFormat(),
        ]);
    }
}
