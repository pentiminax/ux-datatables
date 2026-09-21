<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\Rendering\ColumnKeyResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;

final class ColumnResolver
{
    private readonly AuthorizationChecker $permissionChecker;

    public function __construct(
        private readonly ?AttributeColumnReader $attributeColumnReader = null,
        private readonly ?ColumnAutoDetector $columnAutoDetector = null,
        ?AuthorizationChecker $permissionChecker = null,
    ) {
        $this->permissionChecker = $permissionChecker ?? new AuthorizationChecker();
    }

    /**
     * Resolve columns down a single chain, the first level that declares anything winning.
     *
     * The fluent API sits above this: `AbstractDataTable` only asks when `configureColumns()` left
     * the table empty.
     *
     * @param bool    $apiPlatform    Whether the table opted in through the fluent `->apiPlatform()`
     * @param ?string $dataTableClass Table class, read for the columns no member of the entity backs
     *
     * @return AbstractColumn[]
     */
    public function resolveColumns(?AsDataTable $asDataTable, bool $apiPlatform = false, ?string $dataTableClass = null): array
    {
        $columns = $this->columnsFromClassAttributes($dataTableClass);
        if ([] !== $columns) {
            return $columns;
        }

        $columns = $this->columnsFromAttributes($asDataTable);
        if ([] !== $columns) {
            return $columns;
        }

        return $this->autoDetectColumns($asDataTable, [], $apiPlatform);
    }

    /**
     * Build columns from the column attributes carried by the data class.
     *
     * @return AbstractColumn[]
     */
    public function columnsFromAttributes(?AsDataTable $asDataTable): array
    {
        if (null === $asDataTable) {
            return [];
        }

        return $this->reader()->readColumns($asDataTable->dataClass);
    }

    /**
     * Build columns from the column attributes carried by the table class itself.
     *
     * @param ?class-string $dataTableClass
     *
     * @return AbstractColumn[]
     */
    public function columnsFromClassAttributes(?string $dataTableClass): array
    {
        if (null === $dataTableClass) {
            return [];
        }

        return $this->reader()->readClassColumns($dataTableClass);
    }

    private function reader(): AttributeColumnReader
    {
        return $this->attributeColumnReader ?? new AttributeColumnReader();
    }

    /**
     * Auto-detect columns from API Platform metadata.
     *
     * Returns an empty array when auto-detection is not available (API Platform not installed,
     * no #[AsDataTable] attribute, no API Platform opt-in, or entity is not an ApiResource).
     *
     * @param string[] $groups      Serialization groups to filter properties (defaults to AsDataTable::$serializationGroups)
     * @param bool     $apiPlatform Whether the table opted in through the fluent `->apiPlatform()`
     *
     * @return AbstractColumn[]
     */
    public function autoDetectColumns(?AsDataTable $asDataTable, array $groups = [], bool $apiPlatform = false): array
    {
        if (null === $this->columnAutoDetector) {
            return [];
        }

        if (null === $asDataTable) {
            return [];
        }

        if (!$asDataTable->apiPlatform && !$apiPlatform) {
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
    public function filterStaticPermissions(array $columns, ?string $dataTableClass = null): array
    {
        $filtered = [];

        foreach ($columns as $column) {
            $permission = $column->getPermission();

            if (null !== $permission && !$this->permissionChecker->isGranted($permission)) {
                continue;
            }

            if ($column instanceof ActionColumn && null !== $column->getActions()) {
                $column = clone $column;
                $column->getActions()?->filterStaticPermissions($this->permissionChecker, $dataTableClass);
            }

            $filtered[] = $column;
        }

        return array_values($filtered);
    }

    /**
     * Columns a server-side export writes: exportable and visible in the table.
     *
     * A hidden column is not part of what the user sees, and a TemplateColumn or an ActionColumn
     * carries markup rather than data, so both stay out unless setExportable(true) opts them back in.
     *
     * @param iterable<ColumnInterface> $columns
     *
     * @return list<ColumnInterface>
     */
    public function filterExportable(iterable $columns): array
    {
        $exportable = [];

        foreach ($columns as $column) {
            if ($column->isExportable() && $column->isVisible()) {
                $exportable[] = $column;
            }
        }

        return $exportable;
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
            if (null === $key) {
                continue;
            }

            $this->unsetRowPath($row, $key);

            $readPath = ColumnKeyResolver::readPath($column, $key);
            if ($readPath !== $key) {
                $this->unsetRowPath($row, $readPath);
            }
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function unsetRowPath(array &$row, string $path): void
    {
        unset($row[$path]);

        if (!str_contains($path, '.')) {
            return;
        }

        $this->unsetNestedSegments($row, explode('.', $path));
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string>         $segments
     */
    private function unsetNestedSegments(array &$row, array $segments): void
    {
        $segment = array_shift($segments);
        if (!\is_string($segment) || !\array_key_exists($segment, $row)) {
            return;
        }

        if ([] === $segments) {
            unset($row[$segment]);

            return;
        }

        if (!\is_array($row[$segment])) {
            $normalized = $this->arrayFromNestedValue($row[$segment]);
            if (null === $normalized) {
                return;
            }

            $row[$segment] = $normalized;
        }

        $this->unsetNestedSegments($row[$segment], $segments);
    }

    /**
     * Copy an object-backed nested segment into an array without mutating the original.
     *
     * @return array<string, mixed>|null
     */
    private function arrayFromNestedValue(mixed $value): ?array
    {
        if ($value instanceof \JsonSerializable) {
            $serialized = $value->jsonSerialize();

            return \is_array($serialized) ? $serialized : null;
        }

        if ($value instanceof \ArrayObject) {
            return $value->getArrayCopy();
        }

        if ($value instanceof \Traversable && !$value instanceof \Generator) {
            return iterator_to_array($value);
        }

        if (!\is_object($value)) {
            return null;
        }

        $publicProperties = get_object_vars($value);
        if ([] !== $publicProperties) {
            return $publicProperties;
        }

        try {
            $decoded = json_decode(json_encode($value, \JSON_THROW_ON_ERROR), true);
        } catch (\JsonException) {
            return null;
        }

        return \is_array($decoded) ? $decoded : null;
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
