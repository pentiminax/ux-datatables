<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Enum\IconSize;

class IconColumn extends AbstractColumn
{
    public const string OPTION_IS_ICON       = 'isIcon';
    public const string OPTION_ICONS         = 'icons';
    public const string OPTION_DEFAULT_ICON  = 'defaultIcon';
    public const string OPTION_COLORS        = 'colors';
    public const string OPTION_DEFAULT_COLOR = 'defaultColor';
    public const string OPTION_SIZE          = 'size';
    public const string OPTION_TOOLTIPS      = 'tooltips';
    public const string OPTION_BOOLEAN       = 'boolean';
    public const string OPTION_TRUE_ICON     = 'trueIcon';
    public const string OPTION_FALSE_ICON    = 'falseIcon';
    public const string OPTION_TRUE_COLOR    = 'trueColor';
    public const string OPTION_FALSE_COLOR   = 'falseColor';

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::HTML);
        $column->setCustomOption(self::OPTION_IS_ICON, true);

        return $column;
    }

    /**
     * @param array<array-key, string|Icon> $icons map of cell value => icon name
     */
    public function icons(array $icons): static
    {
        $this->setCustomOption(self::OPTION_ICONS, array_map(self::iconValue(...), $icons));

        return $this;
    }

    public function defaultIcon(string|Icon $icon): static
    {
        $this->setCustomOption(self::OPTION_DEFAULT_ICON, self::iconValue($icon));

        return $this;
    }

    /**
     * @param array<array-key, string> $colors map of cell value => variant (success, warning, ...)
     */
    public function colors(array $colors): static
    {
        $this->setCustomOption(self::OPTION_COLORS, $colors);

        return $this;
    }

    public function defaultColor(string $color): static
    {
        $this->setCustomOption(self::OPTION_DEFAULT_COLOR, $color);

        return $this;
    }

    public function size(string|IconSize $size): static
    {
        $this->setCustomOption(self::OPTION_SIZE, $size instanceof IconSize ? $size->value : $size);

        return $this;
    }

    /**
     * @param array<array-key, string> $tooltips map of cell value => title attribute
     */
    public function tooltips(array $tooltips): static
    {
        $this->setCustomOption(self::OPTION_TOOLTIPS, $tooltips);

        return $this;
    }

    public function boolean(bool $boolean = true): static
    {
        $this->setCustomOption(self::OPTION_BOOLEAN, $boolean);

        return $this;
    }

    public function trueIcon(string|Icon $icon): static
    {
        $this->setCustomOption(self::OPTION_TRUE_ICON, self::iconValue($icon));

        return $this;
    }

    public function falseIcon(string|Icon $icon): static
    {
        $this->setCustomOption(self::OPTION_FALSE_ICON, self::iconValue($icon));

        return $this;
    }

    public function trueColor(string $color): static
    {
        $this->setCustomOption(self::OPTION_TRUE_COLOR, $color);

        return $this;
    }

    public function falseColor(string $color): static
    {
        $this->setCustomOption(self::OPTION_FALSE_COLOR, $color);

        return $this;
    }

    private static function iconValue(string|Icon $icon): string
    {
        return $icon instanceof Icon ? $icon->value : $icon;
    }
}
