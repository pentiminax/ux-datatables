<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;

/**
 * The bulk actions a table offers over its selected rows, plus the selection rules that apply to
 * them. Declared through {@see AbstractDataTable::configureBulkActions()}.
 */
final class BulkActions implements \JsonSerializable
{
    /** @var array<string, BulkAction> */
    private array $actions = [];

    private bool $selectCurrentPageOnly = false;

    private string $idField = 'id';

    private string $position = 'topStart';

    public function add(BulkAction $action): self
    {
        $name = $action->getName();

        if (isset($this->actions[$name])) {
            throw new \InvalidArgumentException(\sprintf('Bulk action name "%s" is already used.', $name));
        }

        $this->actions[$name] = $action;

        return $this;
    }

    public function remove(string $name): self
    {
        unset($this->actions[$name]);

        return $this;
    }

    /**
     * Restrict the user to selecting one page at a time.
     *
     * The bar then offers no "select every matching row" banner, and the endpoint rejects a
     * request that asks for one.
     */
    public function selectCurrentPageOnly(bool $currentPageOnly = true): self
    {
        $this->selectCurrentPageOnly = $currentPageOnly;

        return $this;
    }

    public function isSelectCurrentPageOnly(): bool
    {
        return $this->selectCurrentPageOnly;
    }

    /**
     * Property holding the row identifier sent back to the bulk endpoint (default: `id`).
     */
    public function setIdField(string $idField): self
    {
        $this->idField = $idField;

        return $this;
    }

    public function getIdField(): string
    {
        return $this->idField;
    }

    /**
     * DataTables layout position hosting the bulk action bar (e.g. `topStart`, `topEnd`).
     */
    public function position(string $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getPosition(): string
    {
        return $this->position;
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
     * @return BulkAction[]
     */
    public function getActions(): array
    {
        return array_values($this->actions);
    }

    public function get(string $name): ?BulkAction
    {
        return $this->actions[$name] ?? null;
    }

    /**
     * Remove actions whose static permission is not granted. Mutates in place, so callers that
     * must not affect the container-shared table clone the collection first.
     */
    public function filterStaticPermissions(AuthorizationChecker $checker, ?string $dataTableClass = null): self
    {
        foreach ($this->actions as $key => $action) {
            if (!$action->hasStaticPermission()) {
                continue;
            }

            if (!$checker->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
                $dataTableClass ?? '',
                $action,
                null,
                false,
            ))) {
                unset($this->actions[$key]);
            }
        }

        return $this;
    }

    public function __clone(): void
    {
        $this->actions = array_map(static fn (BulkAction $action): BulkAction => clone $action, $this->actions);
    }

    public function jsonSerialize(): array
    {
        return array_values(array_map(
            static fn (BulkAction $action): array => $action->jsonSerialize(),
            $this->actions,
        ));
    }
}
