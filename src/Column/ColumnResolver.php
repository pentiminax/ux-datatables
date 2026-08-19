<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\Rendering\ColumnKeyResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\PermissionAwareColumnInterface;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;

final class ColumnResolver
{
    private readonly PermissionChecker $permissionChecker;

    public function __construct(
        private readonly ?AttributeColumnReader $attributeColumnReader = null,
        private readonly ?ColumnAutoDetectorInterface $columnAutoDetector = null,
        ?PermissionChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new PermissionChecker();
    }

    /**
     * Resolve columns using the fallback chain: attributes → auto-detect.
     *
     * @return AbstractColumn[]
     */
    public function resolveColumns(?AsDataTable $asDataTable): array
    {
        $columns = $this->columnsFromAttributes($asDataTable);
        if ([] !== $columns) {
            return $columns;
        }

        return $this->autoDetectColumns($asDataTable);
    }

    /**
     * Build columns from #[Column] attributes on the entity class.
     *
     * @return AbstractColumn[]
     */
    public function columnsFromAttributes(?AsDataTable $asDataTable): array
    {
        $reader = $this->attributeColumnReader ?? new AttributeColumnReader();

        if (null === $asDataTable) {
            return [];
        }

        return $reader->readColumns($asDataTable->entityClass);
    }

    /**
     * Auto-detect columns from API Platform metadata.
     *
     * Returns an empty array when auto-detection is not available (API Platform not installed,
     * no #[AsDataTable] attribute, or entity is not an ApiResource).
     *
     * @param string[] $groups Serialization groups to filter properties (defaults to AsDataTable::$serializationGroups)
     *
     * @return AbstractColumn[]
     */
    public function autoDetectColumns(?AsDataTable $asDataTable, array $groups = []): array
    {
        if (null === $this->columnAutoDetector) {
            return [];
        }

        if (null === $asDataTable) {
            return [];
        }

        if (!$asDataTable->apiPlatform) {
            return [];
        }

        $resolvedGroups = $groups ?: $asDataTable->serializationGroups;

        if (!$this->columnAutoDetector->supports($asDataTable->entityClass)) {
            return [];
        }

        return $this->columnAutoDetector->detectColumns($asDataTable->entityClass, $resolvedGroups);
    }

    /**
     * Return the columns (and nested actions) the current user may see.
     *
     * The original column objects are left unchanged: ActionColumn instances are
     * cloned before their action collections are filtered, so a container-shared
     * table can be re-filtered on every request.
     *
     * @param ColumnInterface[] $columns
     *
     * @return ColumnInterface[]
     */
    public function filterStaticPermissions(array $columns): array
    {
        $filtered = [];

        foreach ($columns as $column) {
            $permission = $column instanceof PermissionAwareColumnInterface ? $column->getPermission() : null;

            if (null !== $permission && !$this->permissionChecker->isGranted($permission)) {
                continue;
            }

            if ($column instanceof ActionColumn && null !== $column->getActions()) {
                $column = clone $column;
                $column->getActions()?->filterStaticPermissions($this->permissionChecker);
            }

            $filtered[] = $column;
        }

        return array_values($filtered);
    }

    /**
     * Drop values whose column is not authorized, leaving unrelated extra keys intact.
     *
     * @param array<string, mixed> $row
     * @param ColumnInterface[]    $columns
     *
     * @return array<string, mixed>
     */
    public function removeDeniedColumnValues(array $row, array $columns): array
    {
        $visibleNames = [];
        foreach ($this->filterStaticPermissions($columns) as $column) {
            $visibleNames[$column->getName()] = true;
        }

        foreach ($columns as $column) {
            if (isset($visibleNames[$column->getName()])) {
                continue;
            }

            $key = ColumnKeyResolver::rowKey($column);
            if (null !== $key) {
                unset($row[$key]);
            }
        }

        return $row;
    }

    /**
     * Filter actions whose static permission is not granted. Mutates the Actions collection.
     */
    public function filterActionsByStaticPermissions(Actions $actions): void
    {
        $actions->filterStaticPermissions($this->permissionChecker);
    }

    /**
     * Set entity class on Action objects.
     */
    public function configureActionEntityClass(Actions $actions, ?AsDataTable $asDataTable): void
    {
        if (null === $asDataTable) {
            return;
        }

        foreach ($actions->getActions() as $action) {
            if (null !== $action->getEntityClass()) {
                continue;
            }

            $action->setEntityClass($asDataTable->entityClass);
        }
    }
}
