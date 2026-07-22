<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Enum\IconSize;

class IconColumn extends AbstractColumn
{
    public const string OPTION_IS_ICON     = 'isIcon';
    public const string OPTION_ICON        = 'icon';
    public const string OPTION_COLOR       = 'color';
    public const string OPTION_SIZE        = 'size';
    public const string OPTION_TOOLTIPS    = 'tooltips';
    public const string OPTION_BOOLEAN     = 'boolean';
    public const string OPTION_TRUE_ICON   = 'trueIcon';
    public const string OPTION_FALSE_ICON  = 'falseIcon';
    public const string OPTION_TRUE_COLOR  = 'trueColor';
    public const string OPTION_FALSE_COLOR = 'falseColor';

    private ?\Closure $iconResolver  = null;
    private ?\Closure $colorResolver = null;

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::HTML);
        $column->setCustomOption(self::OPTION_IS_ICON, true);

        return $column;
    }

    /**
     * @param string|Icon|callable(mixed):(string|Icon) $icon
     */
    public function icon(string|Icon|callable $icon): static
    {
        if (!\is_string($icon) && !$icon instanceof Icon && \is_callable($icon)) {
            $this->iconResolver = $icon(...);
        } else {
            $this->setCustomOption(self::OPTION_ICON, self::iconValue($icon));
        }

        return $this;
    }

    /**
     * @param string|callable(mixed):string $color
     */
    public function color(string|callable $color): static
    {
        if (!\is_string($color) && \is_callable($color)) {
            $this->colorResolver = $color(...);
        } else {
            $this->setCustomOption(self::OPTION_COLOR, $color);
        }

        return $this;
    }

    /**
     * @return array{icon?: string, color?: string}
     */
    public function resolveIconData(mixed $state): array
    {
        $data = [
            'icon'  => null !== $this->iconResolver ? self::iconValue(($this->iconResolver)($state)) : null,
            'color' => null !== $this->colorResolver ? ($this->colorResolver)($state) : null,
        ];

        return array_filter($data, static fn ($v): bool => null !== $v);
    }

    public function hasResolvers(): bool
    {
        return null !== $this->iconResolver || null !== $this->colorResolver;
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
