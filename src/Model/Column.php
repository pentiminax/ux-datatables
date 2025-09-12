<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ColumnType;

use function array_filter;

class Column implements ColumnInterface
{
    private ?string $cellType = null;

    private ?string $className = null;

    private ?string $name = null;

    private ColumnType $type;

    private ?string $width = null;

    private string $title;

    private bool $orderable = true;

    private bool $searchable = true;

    private bool $visible = true;

    private ?string $data = null;

    private bool $exportable = true;

    public static function new(string $name, string $title, ColumnType $type = ColumnType::STRING, bool $useNameAsDataSource = false): self
    {
        $self = (new self())
            ->setName($name)
            ->setTitle($title)
            ->setType($type);

        if ($useNameAsDataSource) {
            $self->setData($name);
        }

        return $self;
    }

    public function setClassName(string $className): self
    {
        $this->className = $className;

        return $this;
    }

    /**
     * Change the cell type created for the column - either TD cells or TH cells.
     */
    public function setCellType(string $cellType): self
    {
        $this->cellType = $cellType;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Enable or disable ordering on this column.
     */
    public function setOrderable(bool $orderable): self
    {
        $this->orderable = $orderable;

        return $this;
    }

    /**
     * Enable or disable searching on this column.
     */
    public function setSearchable(bool $searchable): self
    {
        $this->searchable = $searchable;

        return $this;
    }

    /**
     * Set the column type - used for filtering and sorting string processing.
     */
    public function setType(ColumnType $type): self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * This parameter can be used to define the width of a column, and may take any CSS value (3em, 20px etc).
     */
    public function setWidth(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Enable or disable the display of this column.
     */
    public function setVisible(bool $visible): self
    {
        $this->visible = $visible;

        return $this;
    }

    /**
     * Set the data source for the column.
     */
    public function setData(string $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function setExportable(bool $exportable): self
    {
        $this->exportable = $exportable;

        return $this;
    }

    public function isExportable(): bool
    {
        return $this->exportable;
    }

    public function jsonSerialize(): array
    {
        $className = $this->className;

        if ($this->exportable === false) {
            $className = sprintf('%s not-exportable', $className ?? '');
        }

        return array_filter([
            'cellType' => $this->cellType,
            'className' => $className,
            'data' => $this->data,
            'name' => $this->name,
            'orderable' => $this->orderable,
            'searchable' => $this->searchable,
            'type' => $this->type->value,
            'width' => $this->width,
            'title' => $this->title,
            'visible' => $this->visible,
        ], fn($value) => $value !== null);
    }
}