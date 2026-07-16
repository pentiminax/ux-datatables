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
     * @param list<mixed> $rows
     */
    public function resolve(?string $entityClass, array $rows): SourceRowMap
    {
        if (null === $entityClass || null === $this->doctrine) {
            return SourceRowMap::empty($this->identifierExtractor);
        }

        $ids = [];
        foreach ($rows as $row) {
            if (\is_array($row) && null !== ($id = $this->identifierExtractor->extract($row))) {
                $ids[(string) $id] = $id;
            }
        }

        if ([] === $ids) {
            return SourceRowMap::empty($this->identifierExtractor);
        }

        $manager = $this->doctrine->getManagerForClass($entityClass);

        if (!$manager instanceof ObjectManager) {
            return SourceRowMap::empty($this->identifierExtractor);
        }

        $metadata = $manager->getClassMetadata($entityClass);
        $idField  = $metadata->getIdentifierFieldNames()[0] ?? 'id';

        $entities = [];
        foreach ($manager->getRepository($entityClass)->findBy([$idField => array_values($ids)]) as $entity) {
            $identifierValues = $metadata->getIdentifierValues($entity);
            $identifier       = reset($identifierValues);
            if (false !== $identifier) {
                $entities[(string) $identifier] = $entity;
            }
        }

        return new SourceRowMap($entities, $this->identifierExtractor);
    }
}
