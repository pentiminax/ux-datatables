<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class EmailColumn extends AbstractColumn
{
    public const string OPTION_OBFUSCATE     = 'obfuscate';
    public const string OPTION_DISPLAY_VALUE = 'displayValue';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::HTML);
    }

    /**
     * Obfuscate the email address to protect against spam bots.
     * The email will be rendered using HTML entities encoding.
     */
    public function obfuscate(bool $obfuscate = true): static
    {
        $this->setCustomOption(self::OPTION_OBFUSCATE, $obfuscate);

        return $this;
    }

    /**
     * Set a custom display text instead of showing the raw email address.
     */
    public function setDisplayValue(string $displayValue): static
    {
        $this->setCustomOption(self::OPTION_DISPLAY_VALUE, $displayValue);

        return $this;
    }

    public function isObfuscated(): bool
    {
        return (bool) ($this->getCustomOption(self::OPTION_OBFUSCATE) ?? false);
    }

    public function getDisplayValue(): ?string
    {
        return $this->getCustomOption(self::OPTION_DISPLAY_VALUE);
    }
}
