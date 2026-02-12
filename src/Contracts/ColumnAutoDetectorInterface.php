<?php

namespace Pentiminax\UX\DataTables\Contracts;

interface ColumnAutoDetectorInterface
{
    /**
     * Check whether the given entity class supports auto-detection (e.g. is an ApiResource).
     */
    public function supports(string $entityClass): bool;

    /**
     * Auto-detect columns from entity metadata.
     *
     * @param string   $entityClass The FQCN of the entity
     * @param string[] $groups      Serialization groups to filter exposed properties
     *
     * @return ColumnInterface[]
     */
    public function detectColumns(string $entityClass, array $groups = []): array;
}
