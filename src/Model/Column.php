<?php

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ColumnType;

use function array_filter;

class Column
{
    private ?string $cellType = null;

    private ?string $className = null;

    private ?string $name = null;

    private ColumnType $type;

    private ?string $width = null;

    private string $title;

    private bool $orderable = true;

    private bool $searchable = true;


    public static function new(string $name, string $title, ColumnType $type = ColumnType::STRING): self
    {
        return (new self())
            ->setName($name)
            ->setTitle($title)
            ->setType($type);
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
     * When operating in client-side processing mode, DataTables can process the data used for the display in each cell in a manner suitable for the action being performed.
     * For example, HTML tags will be removed from the strings used for filter matching, while sort formatting may remove currency symbols to allow currency values to be sorted numerically.
     * The formatting action performed to normalise the data so it can be ordered and searched depends upon the column's type.
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

    public function toArray(): array
    {
        return array_filter([
            'cellType' => $this->cellType,
            'className' => $this->className,
            'name' => $this->name,
            'orderable' => $this->orderable,
            'searchable' => $this->searchable,
            'type' => $this->type->value,
            'width' => $this->width,
            'title' => $this->title,
        ], fn($value) => $value !== null);
    }
}