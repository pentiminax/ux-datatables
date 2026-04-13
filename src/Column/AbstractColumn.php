<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * @internal
 */
abstract class AbstractColumn implements ColumnInterface
{
    protected ColumnType $type;
    protected ?string $cellType       = null;
    protected ?string $className      = null;
    protected ?string $name           = null;
    protected ?string $width          = null;
    protected ?string $title          = null;
    protected bool $orderable         = true;
    protected bool $searchable        = true;
    protected bool $visible           = true;
    protected ?string $data           = null;
    protected bool $exportable        = true;
    protected ?string $render         = null;
    protected ?string $defaultContent = null;
    protected ?string $field          = null;
    protected bool $globalSearchable  = true;
    protected array $customOptions    = [];

    /**
     * Convenient factory helper used by concrete columns to set their type.
     *
     * @internal
     */
    protected static function createWithType(string $name, string $title, ColumnType $type): static
    {
        $resolvedTitle = '' === $title ? $name : $title;

        return (new static())
            ->setData($name)
            ->setName($name)
            ->setTitle($resolvedTitle)
            ->setType($type);
    }

    public function setClassName(?string $className): static
    {
        $this->className = $className;

        return $this;
    }

    /**
     * Change the type of HTML cell created for this column (either "td" or "th").
     */
    public function setCellType(?string $cellType): static
    {
        $this->cellType = $cellType;

        return $this;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        if (null === $this->title) {
            $this->title = $name;
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->name ?? '';
    }

    /**
     * Enable or disable ordering on this column.
     */
    public function setOrderable(bool $orderable = true): static
    {
        $this->orderable = $orderable;

        return $this;
    }

    /**
     * Enable or disable searching on this column.
     */
    public function setSearchable(bool $searchable = true): static
    {
        $this->searchable = $searchable;

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    public function isGlobalSearchable(): bool
    {
        return $this->globalSearchable;
    }

    public function disableGlobalSearch(): static
    {
        $this->globalSearchable = false;

        return $this;
    }

    /**
     * Set the column type (used for filtering and sorting string processing).
     */
    public function setType(ColumnType $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Define the width of this column (any valid CSS unit such as "120px", "3em", "20%").
     */
    public function setWidth(?string $width): static
    {
        $this->width = $width;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Control whether this column is visible in the table.
     */
    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Set the data source for this column (e.g. "user.email").
     */
    public function setData(?string $data): static
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Register a JavaScript render callback (stringified function name/body).
     */
    public function setRender(?string $render): static
    {
        $this->render = $render;

        return $this;
    }

    /**
     * Define a fallback content when the data source is null or missing.
     */
    public function setDefaultContent(?string $defaultContent): static
    {
        $this->defaultContent = $defaultContent;

        return $this;
    }

    /**
     * Enable or disable export for this column.
     */
    public function setExportable(bool $exportable): static
    {
        $this->exportable = $exportable;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function getType(): ColumnType
    {
        return $this->type;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function isOrderable(): bool
    {
        return $this->orderable;
    }

    public function getWidth(): ?string
    {
        return $this->width;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getCellType(): ?string
    {
        return $this->cellType;
    }

    public function getRender(): ?string
    {
        return $this->render;
    }

    public function getDefaultContent(): ?string
    {
        return $this->defaultContent;
    }

    public function getCustomOptions(): array
    {
        return $this->customOptions;
    }

    public function isNumber(): bool
    {
        return \in_array($this->type, [ColumnType::NUM, ColumnType::NUM_FMT, ColumnType::HTML_NUM, ColumnType::HTML_NUM_FMT], true);
    }

    public function isDate(): bool
    {
        return ColumnType::DATE === $this->type;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getField(): ?string
    {
        return $this->field ?? $this->name;
    }

    public function setField(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function hideWhenUpdating(bool $hidden = true): static
    {
        $this->customOptions['hideWhenUpdating'] = $hidden;

        return $this;
    }

    public function setCustomOption(string $optionName, mixed $optionValue): static
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
        $className = $this->className;

        if (!$this->exportable) {
            $className = trim(\sprintf('%s not-exportable', $className ?? '')) ?: null;
        }

        return array_filter([
            'cellType'       => $this->cellType,
            'className'      => $className,
            'data'           => $this->data,
            'defaultContent' => $this->defaultContent,
            'name'           => $this->name,
            'orderable'      => $this->orderable,
            'render'         => $this->render,
            'searchable'     => $this->searchable,
            'title'          => $this->title,
            'type'           => $this->type->value,
            'visible'        => $this->visible,
            'width'          => $this->width,
            'field'          => $this->getField(),
            'customOptions'  => $this->customOptions,
        ], static fn (mixed $value) => null !== $value && '' !== $value && [] !== $value);
    }
}
