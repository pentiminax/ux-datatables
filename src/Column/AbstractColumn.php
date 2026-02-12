<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Dto\ColumnDto;
use Pentiminax\UX\DataTables\Enum\ColumnType;

abstract class AbstractColumn implements ColumnInterface
{
    protected ColumnDto $dto;

    public function __construct(?ColumnDto $dto = null)
    {
        $this->dto = $dto ?? new ColumnDto();
    }

    /**
     * Convenient factory helper used by concrete columns to set their type.
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
        $this->dto->setClassName($className);

        return $this;
    }

    /**
     * Change the type of HTML cell created for this column (either "td" or "th").
     */
    public function setCellType(?string $cellType): static
    {
        $this->dto->setCellType($cellType);

        return $this;
    }

    public function setName(string $name): static
    {
        $this->dto->setName($name);

        if (null === $this->dto->getTitle()) {
            $this->dto->setTitle($name);
        }

        return $this;
    }

    public function getName(): string
    {
        return $this->dto->getName();
    }

    /**
     * Enable or disable ordering on this column.
     */
    public function setOrderable(bool $orderable = true): static
    {
        $this->dto->setOrderable($orderable);

        return $this;
    }

    /**
     * Enable or disable searching on this column.
     */
    public function setSearchable(bool $searchable = true): static
    {
        $this->dto->setSearchable($searchable);

        return $this;
    }

    public function isSearchable(): bool
    {
        return $this->dto->isSearchable();
    }

    public function isGlobalSearchable(): bool
    {
        return $this->dto->isGlobalSearchable();
    }

    public function disableGlobalSearch(): static
    {
        $this->dto->disableGlobalSearch();

        return $this;
    }

    /**
     * Set the column type (used for filtering and sorting string processing).
     */
    public function setType(ColumnType $type): static
    {
        $this->dto->setType($type);

        return $this;
    }

    /**
     * Define the width of this column (any valid CSS unit such as "120px", "3em", "20%").
     */
    public function setWidth(?string $width): static
    {
        $this->dto->setWidth($width);

        return $this;
    }

    public function setTitle(string $title): static
    {
        $this->dto->setTitle($title);

        return $this;
    }

    /**
     * Control whether this column is visible in the table.
     */
    public function setVisible(bool $visible): static
    {
        $this->dto->setVisible($visible);

        return $this;
    }

    /**
     * Set the data source for this column (e.g. "user.email").
     */
    public function setData(?string $data): static
    {
        $this->dto->setData($data);

        return $this;
    }

    /**
     * Register a JavaScript render callback (stringified function name/body).
     */
    public function setRender(?string $render): static
    {
        $this->dto->setRender($render);

        return $this;
    }

    /**
     * Define a fallback content when the data source is null or missing.
     */
    public function setDefaultContent(?string $defaultContent): static
    {
        $this->dto->setDefaultContent($defaultContent);

        return $this;
    }

    /**
     * Enable or disable export for this column.
     */
    public function setExportable(bool $exportable): static
    {
        $this->dto->setExportable($exportable);

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->dto->isExportable();
    }

    public function isNumber(): bool
    {
        return \in_array($this->dto->getType(), [ColumnType::NUM, ColumnType::NUM_FMT, ColumnType::HTML_NUM, ColumnType::HTML_NUM_FMT]);
    }

    public function isDate(): bool
    {
        return ColumnType::DATE === $this->dto->getType();
    }

    public function getData(): ?string
    {
        return $this->dto->getData();
    }

    public function getField(): ?string
    {
        return $this->dto->getField();
    }

    public function setField(string $field): static
    {
        $this->dto->setField($field);

        return $this;
    }

    public function setCustomOption(string $optionName, mixed $optionValue): static
    {
        $this->dto->setCustomOption($optionName, $optionValue);

        return $this;
    }

    public function getCustomOption(string $optionName): mixed
    {
        return $this->dto->getCustomOption($optionName);
    }

    /**
     * Convert the column to a JSON-serializable array for DataTables initialization.
     */
    public function jsonSerialize(): array
    {
        $className = $this->dto->getClassName();

        if (!$this->dto->isExportable()) {
            $className = trim(sprintf('%s not-exportable', $className ?? '')) ?: null;
        }

        return \array_filter([
            'cellType'       => $this->dto->getCellType(),
            'className'      => $className,
            'data'           => $this->dto->getData(),
            'defaultContent' => $this->dto->getDefaultContent(),
            'name'           => $this->dto->getName(),
            'orderable'      => $this->dto->isOrderable(),
            'render'         => $this->dto->getRender(),
            'searchable'     => $this->dto->isSearchable(),
            'title'          => $this->dto->getTitle(),
            'type'           => $this->dto->getType()->value,
            'visible'        => $this->dto->isVisible(),
            'width'          => $this->dto->getWidth(),
        ], static fn (mixed $value) => null !== $value && '' !== $value);
    }

    /**
     * Get the internal DTO representation of this column.
     */
    public function getAsDto(): ColumnDto
    {
        return $this->dto;
    }
}
