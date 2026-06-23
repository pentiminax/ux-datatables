<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class EmailColumn extends AbstractColumn
{
    public const string OPTION_IS_EMAIL       = 'isEmail';
    public const string OPTION_OBFUSCATE      = 'obfuscate';
    public const string OPTION_MASK           = 'mask';
    public const string OPTION_DISPLAY_VALUE  = 'displayValue';
    public const string OPTION_RENDER_AS_TEXT = 'renderAsText';

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::HTML);
        $column->setCustomOption(self::OPTION_IS_EMAIL, true);

        return $column;
    }

    /**
     * Obfuscates the mailto href in the HTML source to deter email scrapers.
     * The email remains fully visible and clickable to the user.
     */
    public function obfuscate(bool $obfuscate = true): static
    {
        $this->setCustomOption(self::OPTION_OBFUSCATE, $obfuscate);

        return $this;
    }

    /**
     * Masks part of the email address for privacy: e***@example.com
     * The mailto href always contains the full email address.
     */
    public function mask(bool $mask = true): static
    {
        $this->setCustomOption(self::OPTION_MASK, $mask);

        return $this;
    }

    /**
     * Sets a custom display text instead of the email address.
     * Takes priority over mask() when both are set.
     */
    public function setDisplayValue(string $displayValue): static
    {
        $this->setCustomOption(self::OPTION_DISPLAY_VALUE, $displayValue);

        return $this;
    }

    /**
     * Renders the email as plain text instead of a mailto link.
     */
    public function renderAsText(bool $renderAsText = true): static
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_TEXT, $renderAsText);

        return $this;
    }
}
