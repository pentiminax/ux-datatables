<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;

final class EntityLocator
{
    public function __construct(
        private readonly ?ManagerRegistry $doctrine = null,
    ) {
    }

    /**
     * @throws EntityNotFoundException when no persisted entity matches the class/id
     */
    public function locate(string $entityClass, int|string|null $id): MutationContext
    {
        if (null === $this->doctrine || '' === $entityClass || null === $id) {
            throw new EntityNotFoundException();
        }

        $manager = $this->doctrine->getManagerForClass($entityClass);

        if (!$manager instanceof ObjectManager) {
            throw new EntityNotFoundException();
        }

        /** @var ObjectRepository<object> $repository */
        $repository = $manager->getRepository($entityClass);
        $entity     = $repository->find($id);

        if (!\is_object($entity)) {
            throw new EntityNotFoundException();
        }

        return new MutationContext($entity, $manager);
    }
}
