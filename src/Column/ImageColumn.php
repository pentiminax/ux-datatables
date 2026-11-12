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

    /**
     * @deprecated since 1.6, use imageWidth() instead. Removed in 2.0.
     */
    public function setImageWidth(int $px): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "imageWidth()" instead.', __METHOD__);

        return $this->imageWidth($px);
    }

    public function imageHeight(int $px): static
    {
        $this->customOption(self::OPTION_IMAGE_HEIGHT, $px);

        return $this;
    }

    /**
     * @deprecated since 1.6, use imageHeight() instead. Removed in 2.0.
     */
    public function setImageHeight(int $px): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "imageHeight()" instead.', __METHOD__);

        return $this->imageHeight($px);
    }

    public function alt(string $alt): static
    {
        $this->customOption(self::OPTION_ALT, $alt);

        return $this;
    }

    /**
     * @deprecated since 1.6, use alt() instead. Removed in 2.0.
     */
    public function setAlt(string $alt): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "alt()" instead.', __METHOD__);

        return $this->alt($alt);
    }

    public function placeholder(string $url): static
    {
        $this->customOption(self::OPTION_PLACEHOLDER, $url);

        return $this;
    }

    /**
     * @deprecated since 1.6, use placeholder() instead. Removed in 2.0.
     */
    public function setPlaceholder(string $url): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "placeholder()" instead.', __METHOD__);

        return $this->placeholder($url);
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
