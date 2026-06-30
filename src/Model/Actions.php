<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Security\PermissionChecker;

final class Actions implements \JsonSerializable
{
    /** @var Action[] */
    private array $actions = [];

    private string $columnLabel = 'Actions';

    private ?string $columnClassName = null;

    private ActionsPosition $position = ActionsPosition::AfterColumns;

    private ?ActionsAlignment $alignment = null;

    public function add(Action $action): self
    {
        $this->actions[$action->getName()] = $action;

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

    /**
     * Place the actions column before or after the data columns (default: after).
     */
    public function position(ActionsPosition $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): ActionsPosition
    {
        return $this->position;
    }

    /**
     * Horizontally align the actions cell (default: framework default, no class added).
     */
    public function alignment(ActionsAlignment $alignment): self
    {
        $this->alignment = $alignment;

        return $this;
    }

    public function getAlignment(): ?ActionsAlignment
    {
        return $this->alignment;
    }

    /**
     * Split the actions into position-grouped collections.
     *
     * Each action is placed in the group matching its own position when set,
     * otherwise in the group matching the collection-level position. Every
     * returned collection inherits this collection's column metadata (label,
     * class name, alignment). Only non-empty groups are returned, preserving
     * the order in which positions are first encountered.
     *
     * @return array<value-of<ActionsPosition>, self>
     */
    public function partitionByPosition(): array
    {
        $groups = [];

        foreach ($this->actions as $action) {
            $position = ($action->getPosition() ?? $this->position)->value;

            ($groups[$position] ??= $this->withoutActions())->add($action);
        }

        return $groups;
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

    /**
     * Remove actions whose static permission is not granted. Mutates in place.
     */
    public function filterStaticPermissions(PermissionChecker $checker): self
    {
        foreach ($this->actions as $key => $action) {
            if (!$action->hasStaticPermission()) {
                continue;
            }

            if (!$checker->isGranted((string) $action->getPermission())) {
                unset($this->actions[$key]);
            }
        }

        return $this;
    }

    /**
     * Create an empty collection that copies this one's column metadata.
     */
    private function withoutActions(): self
    {
        $clone                  = new self();
        $clone->columnLabel     = $this->columnLabel;
        $clone->columnClassName = $this->columnClassName;
        $clone->position        = $this->position;
        $clone->alignment       = $this->alignment;

        return $clone;
    }

    public function jsonSerialize(): array
    {
        return array_values(array_map(
            static fn (Action $action): array => $action->jsonSerialize(),
            $this->actions
        ));
    }
}
