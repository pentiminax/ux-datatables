<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Form;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;

final class EditFormEntityResolver
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
    ) {
    }

    public function resolve(string $entityClass, int|string|null $id): ?EditFormEntityContext
    {
        if ('' === $entityClass || null === $id) {
            return null;
        }

        $manager = $this->doctrine->getManagerForClass($entityClass);

        if (!$manager instanceof ObjectManager) {
            return null;
        }

        /** @var ObjectRepository<object> $repository */
        $repository = $manager->getRepository($entityClass);
        $entity     = $repository->find($id);

        if (!\is_object($entity)) {
            return null;
        }

        return new EditFormEntityContext(
            entity: $entity,
            manager: $manager,
            identifierFields: $manager->getClassMetadata($entityClass)->getIdentifierFieldNames(),
        );
    }
}
