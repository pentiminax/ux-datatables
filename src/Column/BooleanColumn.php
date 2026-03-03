<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class BooleanColumn extends AbstractColumn
{
    public const string OPTION_RENDER_AS_SWITCH = 'renderAsSwitch';
    public const string OPTION_DEFAULT_STATE    = 'defaultState';
    public const string OPTION_TOGGLE_METHOD    = 'toggleMethod';
    public const string OPTION_TOGGLE_ID_FIELD  = 'toggleIdField';
    public const string OPTION_ENTITY_CLASS     = 'entityClass';
    public const string OPTION_TOGGLE_FIELD     = 'booleanToggleField';

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
        $this->setCustomOption(self::OPTION_ENTITY_CLASS, ltrim($entityClass, '\\'));

        return $this;
    }

    public function getEntityClass(): ?string
    {
        return $this->getCustomOption(self::OPTION_ENTITY_CLASS);
    }

    public function isRenderedAsSwitch(): bool
    {
        return $this->getCustomOption(self::OPTION_RENDER_AS_SWITCH) ?? true;
    }

    public function getDefaultState(): bool
    {
        return $this->getCustomOption(self::OPTION_DEFAULT_STATE) ?? false;
    }
}
