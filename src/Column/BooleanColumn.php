<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Enum\ColumnType;

class BooleanColumn extends AbstractColumn
{
    public const OPTION_RENDER_AS_SWITCH = 'renderAsSwitch';
    public const OPTION_DEFAULT_STATE    = 'defaultState';
    public const OPTION_TOGGLE_METHOD    = 'toggleMethod';
    public const OPTION_TOGGLE_ID_FIELD  = 'toggleIdField';
    public const OPTION_ENTITY_CLASS     = 'entityClass';
    public const OPTION_TOGGLE_FIELD     = 'toggleField';

    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::NUM);
    }

    /**
     * Enable interactive switch rendering for boolean values.
     *
     * The Stimulus controller renders a Bootstrap 5 form switch or a Tailwind
     * toggle according to the detected DataTables style framework.
     */
    public function renderAsSwitch(bool $defaultState = false): static
    {
        $this->customOption(self::OPTION_RENDER_AS_SWITCH, true);
        $this->customOption(self::OPTION_DEFAULT_STATE, $defaultState);

        return $this;
    }

    public function toggleAjax(string $idField = 'id', string $method = 'PATCH'): static
    {
        $this->customOption(self::OPTION_TOGGLE_ID_FIELD, $idField);
        $this->customOption(self::OPTION_TOGGLE_METHOD, strtoupper($method));

        return $this;
    }

    /**
     * @deprecated since 1.6, use toggleAjax() instead. Removed in 2.0.
     */
    public function setToggleAjax(string $idField = 'id', string $method = 'PATCH'): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "toggleAjax()" instead.', __METHOD__);

        return $this->toggleAjax($idField, $method);
    }

    public function entityClass(string $entityClass): static
    {
        $this->customOption(self::OPTION_ENTITY_CLASS, ltrim($entityClass, '\\'));

        return $this;
    }

    /**
     * @deprecated since 1.6, use entityClass() instead. Removed in 2.0.
     */
    public function setEntityClass(string $entityClass): static
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'The "%s()" method is deprecated, use "entityClass()" instead.', __METHOD__);

        return $this->entityClass($entityClass);
    }

    public function getEntityClass(): ?string
    {
        return $this->getCustomOption(self::OPTION_ENTITY_CLASS);
    }

    public function isRenderedAsSwitch(): bool
    {
        return $this->getCustomOption(self::OPTION_RENDER_AS_SWITCH) ?? false;
    }

    public function getDefaultState(): bool
    {
        return $this->getCustomOption(self::OPTION_DEFAULT_STATE) ?? false;
    }

    public function getToggleField(): ?string
    {
        return $this->getCustomOption(self::OPTION_TOGGLE_FIELD);
    }

    public function jsonSerialize(): array
    {
        $options = parent::jsonSerialize();

        if (isset($options['customOptions']) && \is_array($options['customOptions'])) {
            unset($options['customOptions'][self::OPTION_ENTITY_CLASS]);

            if ([] === $options['customOptions']) {
                unset($options['customOptions']);
            }
        }

        return $options;
    }
}
