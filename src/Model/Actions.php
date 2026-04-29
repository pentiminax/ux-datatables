<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ActionType;

final class Actions implements \JsonSerializable
{
    /** @var Action[] */
    private array $actions = [];

    private string $columnLabel = 'Actions';

    private ?string $columnClassName = null;

    public function add(Action $action): self
    {
        $this->actions[$action->getType()->value] = $action;

        return $this;
    }

    public function remove(ActionType $type): self
    {
        unset($this->actions[$type->value]);

        return $this;
    }

    public function setColumnLabel(string $label): self
    {
        $this->columnLabel = $label;

        return $this;
    }

    public function getColumnLabel(): string
    {
        return $this->columnLabel;
    }

    public function setColumnClassName(?string $className): self
    {
        $this->columnClassName = $className;

        return $this;
    }

    public function getColumnClassName(): ?string
    {
        return $this->columnClassName;
    }

    public function isEmpty(): bool
    {
        return [] === $this->actions;
    }

    public function count(): int
    {
        return \count($this->actions);
    }

    /**
     * @return Action[]
     */
    public function getActions(): array
    {
        return array_values($this->actions);
    }

    public function jsonSerialize(): array
    {
        return array_values(array_map(
            static fn (Action $action): array => $action->jsonSerialize(),
            $this->actions
        ));
    }
}
