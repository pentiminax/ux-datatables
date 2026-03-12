<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Dto\ColumnDto;
use Pentiminax\UX\DataTables\Enum\ActionType;
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
            action: ActionType::Delete,
            actionLabel: '',
            actionUrl: '',
        );

        $instance->actions = $actions;

        return $instance;
    }

    private function __construct(
        private string $name,
        private string $title,
        private ActionType $action,
        private string $actionLabel,
        private string $actionUrl,
    ) {
        $this->dto = new ColumnDto();
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

    public function jsonSerialize(): array
    {
        $base = [
            'data'       => null,
            'className'  => 'not-exportable',
            'name'       => $this->name,
            'title'      => $this->title,
            'orderable'  => false,
            'searchable' => false,
        ];

        if (null !== $this->actions) {
            $base['actions'] = $this->actions->jsonSerialize();
        } else {
            $base['action']      = $this->action->value;
            $base['actionLabel'] = $this->actionLabel;
            $base['actionUrl']   = $this->actionUrl;
        }

        return $base;
    }
}
