<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Rehydration;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;

final class SourceRowResolver
{
    public function __construct(
        private readonly RowIdentifierExtractor $identifierExtractor,
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
    }

    /**
     * Batch-rehydrate the source entity backing each row in a single query.
     *
     * The returned array preserves the keys of $rows: each entry holds the
     * resolved entity, or null when the row carries no resolvable identifier
     * or no matching entity exists. The identifier is extracted exactly once
     * per row.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return array<array-key, object|null>
     */
    public function resolve(?string $entityClass, array $rows): array
    {
        $resolved = array_fill_keys(array_keys($rows), null);

        if (null === $entityClass || null === $this->doctrine) {
            return $resolved;
        }

        $idByRowKey = [];
        $ids        = [];
        foreach ($rows as $key => $row) {
            if (\is_array($row) && null !== ($id = $this->identifierExtractor->extract($row))) {
                $idByRowKey[$key]  = (string) $id;
                $ids[(string) $id] = $id;
            }
        }

        if ([] === $ids) {
            return $resolved;
        }

        $manager = $this->doctrine->getManagerForClass($entityClass);

        if (!$manager instanceof ObjectManager) {
            return $resolved;
        }

        $metadata         = $manager->getClassMetadata($entityClass);
        $identifierFields = $metadata->getIdentifierFieldNames();

        // Rows carry a single scalar identifier, so composite keys cannot be
        // matched reliably; skip rehydration rather than silently mismatch rows.
        if (1 !== \count($identifierFields)) {
            return $resolved;
        }

        $entities = [];
        foreach ($manager->getRepository($entityClass)->findBy([$identifierFields[0] => array_values($ids)]) as $entity) {
            $identifierValues = $metadata->getIdentifierValues($entity);
            $identifier       = reset($identifierValues);
            if (false !== $identifier) {
                $entities[(string) $identifier] = $entity;
            }
        }

        foreach ($idByRowKey as $key => $id) {
            $resolved[$key] = $entities[$id] ?? null;
        }

        return $resolved;
    }
}
