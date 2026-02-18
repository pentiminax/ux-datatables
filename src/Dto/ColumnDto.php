<?php

namespace Pentiminax\UX\DataTables\Dto;

use Pentiminax\UX\DataTables\Enum\ColumnType;

final class ColumnDto implements \JsonSerializable
{
    private ColumnType $type;
    private ?string $cellType       = null;
    private ?string $className      = null;
    private ?string $name           = null;
    private ?string $width          = null;
    private ?string $title          = null;
    private bool $orderable         = true;
    private bool $searchable        = true;
    private bool $visible           = true;
    private ?string $data           = null;
    private bool $exportable        = true;
    private ?string $render         = null;
    private ?string $defaultContent = null;
    private ?string $field          = null;
    private bool $globalSearchable  = true;
    private array $customOptions    = [];

    public function getCellType(): ?string
    {
        return $this->cellType;
    }

    public function setCellType(?string $cellType): self
    {
        $this->cellType = $cellType;

        return $this;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function setClassName(?string $className): self
    {
        $this->className = $className;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ColumnType
    {
        return $this->type;
    }

    public function setType(ColumnType $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function setWidth(?string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function isOrderable(): bool
    {
        return $this->orderable;
    }

    public function setOrderable(bool $orderable = true): self
    {
        $this->orderable = $orderable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function setSearchable(bool $searchable = true): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible = true): self
    {
        $this->visible = $visible;

        return $this;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function setData(?string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function setExportable(bool $exportable): self
    {
        $this->exportable = $exportable;

        return $this;
    }

    public function getRender(): ?string
    {
        return $this->render;
    }

    public function setRender(?string $render): self
    {
        $this->render = $render;

        return $this;
    }

    public function getDefaultContent(): ?string
    {
        return $this->defaultContent;
    }

    public function setDefaultContent(?string $defaultContent): self
    {
        $this->defaultContent = $defaultContent;

        return $this;
    }

    public function getField(): ?string
    {
        return $this->field ?? $this->name;
    }

    public function setField(?string $field): self
    {
        $this->field = $field;

        return $this;
    }

    public function isGlobalSearchable(): bool
    {
        return $this->globalSearchable;
    }

    public function disableGlobalSearch(): self
    {
        $this->globalSearchable = false;

        return $this;
    }

    public function setCustomOption(string $optionName, mixed $optionValue): self
    {
        $this->customOptions[$optionName] = $optionValue;

        return $this;
    }

    public function getCustomOption(string $optionName): mixed
    {
        return $this->customOptions[$optionName] ?? null;
    }

    public function jsonSerialize(): array
    {
        return [
            'title'          => $this->title,
            'name'           => $this->name,
            'type'           => $this->type->value,
            'data'           => $this->data,
            'cellType'       => $this->cellType,
            'className'      => $this->className,
            'width'          => $this->width,
            'orderable'      => $this->orderable,
            'searchable'     => $this->searchable,
            'visible'        => $this->visible,
            'exportable'     => $this->exportable,
            'render'         => $this->render,
            'defaultContent' => $this->defaultContent,
        ];
    }
}
