<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Dto\ColumnDto;
use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Model\Actions;

class ActionColumn implements ColumnInterface
{
    protected ColumnDto $dto;

    private ?Actions $actions = null;

    public static function fromActions(string $name, string $title, Actions $actions): static
    {
        $instance = new self(
            name: $name,
            title: '' === $title ? $name : $title,
        );

        $instance->actions = $actions;

        return $instance;
    }

    private function __construct(
        private string $name,
        private string $title,
    ) {
        $this->dto = (new ColumnDto())
            ->setName($this->name)
            ->setTitle($this->title)
            ->setOrderable(false)
            ->setSearchable(false)
            ->setType(ColumnType::STRING)
            ->setExportable(false);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getAsDto(): ColumnDto
    {
        return $this->dto;
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

    public function setVisible(bool $visible): static
    {
        $this->dto->setVisible($visible);

        return $this;
    }

    public function isSearchable(): bool
    {
        return false;
    }

    public function isGlobalSearchable(): bool
    {
        return false;
    }

    public function getData(): ?string
    {
        return null;
    }

    public function getActions(): ?Actions
    {
        return $this->actions;
    }

    public function jsonSerialize(): array
    {
        $this->dto->setActions($this->actions?->jsonSerialize());

        return $this->dto->jsonSerialize();
    }
}
