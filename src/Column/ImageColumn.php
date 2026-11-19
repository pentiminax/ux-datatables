<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class ImageColumn extends AbstractColumn
{
    public const OPTION_IS_IMAGE     = 'isImage';
    public const OPTION_IMAGE_WIDTH  = 'imageWidth';
    public const OPTION_IMAGE_HEIGHT = 'imageHeight';
    public const OPTION_ALT          = 'alt';
    public const OPTION_LAZY         = 'lazy';
    public const OPTION_ROUNDED      = 'rounded';
    public const OPTION_PLACEHOLDER  = 'placeholder';
    public const OPTION_CLICKABLE    = 'clickable';

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::HTML);
        $column->customOption(self::OPTION_IS_IMAGE, true);
        $column->customOption(self::OPTION_LAZY, true);

        return $column;
    }

    public function imageWidth(int $px): static
    {
        $this->customOption(self::OPTION_IMAGE_WIDTH, $px);

        return $this;
    }

    public function imageHeight(int $px): static
    {
        $this->customOption(self::OPTION_IMAGE_HEIGHT, $px);

        return $this;
    }

    public function alt(string $alt): static
    {
        $this->customOption(self::OPTION_ALT, $alt);

        return $this;
    }

    public function placeholder(string $url): static
    {
        $this->customOption(self::OPTION_PLACEHOLDER, $url);

        return $this;
    }

    public function rounded(bool $rounded = true): static
    {
        $this->customOption(self::OPTION_ROUNDED, $rounded);

        return $this;
    }

    public function lazy(bool $lazy = true): static
    {
        $this->customOption(self::OPTION_LAZY, $lazy);

        return $this;
    }

    public function clickable(bool $clickable = true): static
    {
        $this->customOption(self::OPTION_CLICKABLE, $clickable);

        return $this;
    }
}
