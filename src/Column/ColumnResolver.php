<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Model\Actions;

final class ColumnResolver
{
    public function __construct(
        private readonly ?AttributeColumnReader $attributeColumnReader = null,
        private readonly ?ColumnAutoDetectorInterface $columnAutoDetector = null,
        private readonly ?UrlColumnResolver $urlColumnResolver = null,
    ) {
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

        $resolvedGroups = $groups ?: $asDataTable->serializationGroups;

        if (!$this->columnAutoDetector->supports($asDataTable->entityClass)) {
            return [];
        }

        return $this->columnAutoDetector->detectColumns($asDataTable->entityClass, $resolvedGroups);
    }

    /**
     * Set entity class on BooleanColumns that don't have one yet.
     *
     * @param ColumnInterface[] $columns
     */
    public function configureBooleanColumns(array $columns, ?AsDataTable $asDataTable): void
    {
        if (null === $asDataTable) {
            return;
        }

        foreach ($columns as $column) {
            if (!$column instanceof BooleanColumn) {
                continue;
            }

            if (null !== $column->getEntityClass()) {
                continue;
            }

            $column->setEntityClass($asDataTable->entityClass);
        }
    }

    /**
     * Resolve URL templates for UrlColumns.
     *
     * @param ColumnInterface[] $columns
     */
    public function configureUrlColumns(array $columns): void
    {
        $this->urlColumnResolver?->resolveRoutes($columns);
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
            $action->setEntityClass($asDataTable->entityClass);
        }
    }
}
