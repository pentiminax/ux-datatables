<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class EmailColumn extends AbstractColumn
{
    public const OPTION_IS_EMAIL       = 'isEmail';
    public const OPTION_OBFUSCATE      = 'obfuscate';
    public const OPTION_MASK           = 'mask';
    public const OPTION_DISPLAY_VALUE  = 'displayValue';
    public const OPTION_RENDER_AS_TEXT = 'renderAsText';

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::HTML);
        $column->customOption(self::OPTION_IS_EMAIL, true);

        return $column;
    }

    /**
     * Obfuscates the mailto href in the HTML source to deter email scrapers.
     * The email remains fully visible and clickable to the user.
     */
    public function obfuscate(bool $obfuscate = true): static
    {
        $this->customOption(self::OPTION_OBFUSCATE, $obfuscate);

        return $this;
    }

    /**
     * Masks part of the email address for privacy: e***@example.com
     * The mailto href always contains the full email address.
     */
    public function mask(bool $mask = true): static
    {
        $this->customOption(self::OPTION_MASK, $mask);

        return $this;
    }

    /**
     * Sets a custom display text instead of the email address.
     * Takes priority over mask() when both are set.
     */
    public function displayValue(string $displayValue): static
    {
        $this->customOption(self::OPTION_DISPLAY_VALUE, $displayValue);

        return $this;
    }

    /**
     * @deprecated since 1.6, use displayValue() instead. Removed in 2.0.
     */
    public function setDisplayValue(string $displayValue): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "displayValue()" instead.', __METHOD__);

        return $this->displayValue($displayValue);
    }

    /**
     * Renders the email as plain text instead of a mailto link.
     */
    public function renderAsText(bool $renderAsText = true): static
    {
        $this->customOption(self::OPTION_RENDER_AS_TEXT, $renderAsText);

        return $this;
    }
}
