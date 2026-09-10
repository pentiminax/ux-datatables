<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class DateColumn extends AbstractColumn
{
    public const string DEFAULT_DATE_FORMAT = 'Y-m-d';
    public const string OPTION_DATE_FORMAT  = 'dateFormat';
    public const string OPTION_RELATIVE     = 'relative';

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

    /**
     * Render the cell as a localized relative label ("3 minutes ago") in the browser, through
     * `Intl.RelativeTimeFormat`.
     *
     * The serialized value switches to ISO 8601 so the client can compute the offset, which makes
     * the format set through setFormat() irrelevant while this is enabled. Ordering still happens
     * on the underlying field, and defaultContent covers null values.
     */
    public function relative(bool $relative = true): self
    {
        if (!$relative) {
            unset($this->customOptions[self::OPTION_RELATIVE]);

            return $this;
        }

        $this->setCustomOption(self::OPTION_RELATIVE, true);

        return $this;
    }

    public function isRelative(): bool
    {
        return true === $this->getCustomOption(self::OPTION_RELATIVE);
    }

    public function getFormat(): ?string
    {
        if ($this->isRelative()) {
            return \DateTimeInterface::ATOM;
        }

        return $this->getCustomOption(self::OPTION_DATE_FORMAT) ?? self::DEFAULT_DATE_FORMAT;
    }
}
