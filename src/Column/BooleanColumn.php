<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class BooleanColumn extends AbstractColumn
{
    public const OPTION_RENDER_AS_SWITCH    = 'booleanRenderAsSwitch';
    public const OPTION_DEFAULT_STATE       = 'booleanDefaultState';
    public const OPTION_TOGGLE_METHOD       = 'booleanToggleMethod';
    public const OPTION_TOGGLE_ID_FIELD     = 'booleanToggleIdField';
    public const OPTION_TOGGLE_ENTITY_CLASS = 'booleanToggleEntityClass';
    public const OPTION_TOGGLE_FIELD        = 'booleanToggleField';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::NUM)
            ->renderAsSwitch();
    }

    public function renderAsSwitch(bool $defaultState = false): self
    {
        $this->setCustomOption(self::OPTION_RENDER_AS_SWITCH, true);
        $this->setCustomOption(self::OPTION_DEFAULT_STATE, $defaultState);

        return $this;
    }

    public function setToggleAjax(string $idField = 'id', string $method = 'PATCH'): self
    {
        $this->setCustomOption(self::OPTION_TOGGLE_ID_FIELD, $idField);
        $this->setCustomOption(self::OPTION_TOGGLE_METHOD, strtoupper($method));

        return $this;
    }

    public function setEntityClass(string $entityClass): self
    {
        $this->setCustomOption(self::OPTION_TOGGLE_ENTITY_CLASS, ltrim($entityClass, '\\'));

        return $this;
    }

    public function getToggleEntityClass(): ?string
    {
        return $this->getCustomOption(self::OPTION_TOGGLE_ENTITY_CLASS);
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
                self::OPTION_RENDER_AS_SWITCH    => $this->isRenderedAsSwitch(),
                self::OPTION_DEFAULT_STATE       => $this->getDefaultState(),
                self::OPTION_TOGGLE_METHOD       => $this->getCustomOption(self::OPTION_TOGGLE_METHOD),
                self::OPTION_TOGGLE_ID_FIELD     => $this->getCustomOption(self::OPTION_TOGGLE_ID_FIELD),
                self::OPTION_TOGGLE_ENTITY_CLASS => $this->getToggleEntityClass(),
                self::OPTION_TOGGLE_FIELD        => $this->getField(),
            ], static fn (mixed $value) => null !== $value && '' !== $value)
        );
    }
}
