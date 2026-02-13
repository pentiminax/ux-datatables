<?php

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Dto\ColumnDto;
use Pentiminax\UX\DataTables\Enum\Action;

class ActionColumn implements ColumnInterface
{
    protected ColumnDto $dto;

    public static function new(
        string $name,
        string $title = '',
        Action $action = Action::DELETE,
        string $actionLabel = '',
        string $actionUrl = '',
    ): static {
        return new self(
            $name,
            '' === $title ? $name : $title,
            $action,
            $actionLabel,
            $actionUrl,
        );
    }

    private function __construct(
        private string $name,
        private string $title,
        private Action $action,
        private string $actionLabel,
        private string $actionUrl,
    ) {
        $this->dto = new ColumnDto();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function jsonSerialize(): array
    {
        return [
            'data'        => null,
            'className'   => 'not-exportable',
            'name'        => $this->name,
            'title'       => $this->title,
            'action'      => $this->action->value,
            'actionLabel' => $this->actionLabel,
            'actionUrl'   => $this->actionUrl,
        ];
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
}
