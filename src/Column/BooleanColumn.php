<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class BooleanColumn extends AbstractColumn
{
    public const OPTION_RENDER_AS_SWITCH = 'booleanRenderAsSwitch';
    public const OPTION_DEFAULT_STATE    = 'booleanDefaultState';
    public const OPTION_TOGGLE_URL       = 'booleanToggleUrl';
    public const OPTION_TOGGLE_METHOD    = 'booleanToggleMethod';
    public const OPTION_TOGGLE_ID_FIELD  = 'booleanToggleIdField';

    public static function new(string $name, string $title = ''): self
    {
        return static::createWithType($name, $title, ColumnType::NUM)
            ->renderAsSwitch(false);
    }

    public function renderAsSwitch(bool $defaultState = false): self
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_SWITCH, true);
        $this->setCustomOption(self::OPTION_DEFAULT_STATE, $defaultState);

        return $this;
    }

    public function setToggleAjax(string $url, string $idField = 'id', string $method = 'PATCH'): self
    {
        $this->setCustomOption(self::OPTION_TOGGLE_URL, $url);
        $this->setCustomOption(self::OPTION_TOGGLE_ID_FIELD, $idField);
        $this->setCustomOption(self::OPTION_TOGGLE_METHOD, strtoupper($method));

        return $this;
    }

    public function isRenderedAsSwitch(): bool
    {
        return $this->getCustomOption(self::OPTION_RENDER_AS_SWITCH) ?? true;
    }

    public function getDefaultState(): bool
    {
        return $this->getCustomOption(self::OPTION_DEFAULT_STATE) ?? false;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            parent::jsonSerialize(),
            array_filter([
                self::OPTION_RENDER_AS_SWITCH => $this->isRenderedAsSwitch(),
                self::OPTION_DEFAULT_STATE    => $this->getDefaultState(),
                self::OPTION_TOGGLE_URL       => $this->getCustomOption(self::OPTION_TOGGLE_URL),
                self::OPTION_TOGGLE_METHOD    => $this->getCustomOption(self::OPTION_TOGGLE_METHOD),
                self::OPTION_TOGGLE_ID_FIELD  => $this->getCustomOption(self::OPTION_TOGGLE_ID_FIELD),
            ], static fn (mixed $value) => null !== $value && '' !== $value)
        );
    }
}
