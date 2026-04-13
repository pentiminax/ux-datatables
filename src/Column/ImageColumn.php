<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class ImageColumn extends AbstractColumn
{
    public const string OPTION_IS_IMAGE     = 'isImage';
    public const string OPTION_IMAGE_WIDTH  = 'imageWidth';
    public const string OPTION_IMAGE_HEIGHT = 'imageHeight';
    public const string OPTION_ALT          = 'alt';
    public const string OPTION_LAZY         = 'lazy';
    public const string OPTION_ROUNDED      = 'rounded';
    public const string OPTION_PLACEHOLDER  = 'placeholder';
    public const string OPTION_CLICKABLE    = 'clickable';

    public static function new(string $name, string $title = ''): static
    {
        $column = static::createWithType($name, $title, ColumnType::HTML);
        $column->setCustomOption(self::OPTION_IS_IMAGE, true);
        $column->setCustomOption(self::OPTION_LAZY, true);

        return $column;
    }

    public function setImageWidth(int $px): static
    {
        $this->setCustomOption(self::OPTION_IMAGE_WIDTH, $px);

        return $this;
    }

    public function setImageHeight(int $px): static
    {
        $this->setCustomOption(self::OPTION_IMAGE_HEIGHT, $px);

        return $this;
    }

    public function setAlt(string $alt): static
    {
        $this->setCustomOption(self::OPTION_ALT, $alt);

        return $this;
    }

    public function setPlaceholder(string $url): static
    {
        $this->setCustomOption(self::OPTION_PLACEHOLDER, $url);

        return $this;
    }

    public function rounded(bool $rounded = true): static
    {
        $this->setCustomOption(self::OPTION_ROUNDED, $rounded);

        return $this;
    }

    public function lazy(bool $lazy = true): static
    {
        $this->setCustomOption(self::OPTION_LAZY, $lazy);

        return $this;
    }

    public function clickable(bool $clickable = true): static
    {
        $this->setCustomOption(self::OPTION_CLICKABLE, $clickable);

        return $this;
    }
}
