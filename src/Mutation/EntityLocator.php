<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\Mapping\ClassMetadata;
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
     * @param string|null $field Doctrine field that holds the submitted id. When it is the primary
     *                           key (or missing / unmapped, e.g. a getter-only `id`), lookup uses
     *                           {@see ObjectRepository::find()}. When it is another mapped field —
     *                           the same contract as {@see \Pentiminax\UX\DataTables\Model\Action::setIdField()}
     *                           and boolean `setToggleAjax(idField:)` — lookup uses findOneBy so a
     *                           numeric SKU cannot be mistaken for another row's primary key.
     *
     * @throws EntityNotFoundException when no persisted entity matches the class/id
     */
    public function locate(string $entityClass, int|string|null $id, ?string $field = null): MutationContext
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
        $entity     = $this->findEntity($manager, $repository, $entityClass, $id, $field);

        if (!\is_object($entity)) {
            throw new EntityNotFoundException();
        }

        return new MutationContext($entity, $manager);
    }

    /**
     * The manager owning the class, without locating any entity.
     *
     * @throws EntityNotFoundException when Doctrine is absent or manages no such class
     */
    public function manager(string $entityClass): ObjectManager
    {
        if (null === $this->doctrine || '' === $entityClass) {
            throw new EntityNotFoundException();
        }

        $manager = $this->doctrine->getManagerForClass($entityClass);

        if (!$manager instanceof ObjectManager) {
            throw new EntityNotFoundException();
        }

        return $manager;
    }

    /**
     * @param ObjectRepository<object> $repository
     */
    private function findEntity(
        ObjectManager $manager,
        ObjectRepository $repository,
        string $entityClass,
        int|string $id,
        ?string $field,
    ): ?object {
        if (null === $field || '' === $field) {
            return $repository->find($id);
        }

        $metadata    = $manager->getClassMetadata($entityClass);
        $identifiers = $metadata->getIdentifierFieldNames();

        if (1 === \count($identifiers) && $field === $identifiers[0]) {
            return $repository->find($id);
        }

        if ($this->isLookupField($metadata, $field)) {
            return $repository->findOneBy([$field => $id]);
        }

        // Action/boolean id fields often name a getter (`id`) while the Doctrine identifier is
        // stored under another property. The submitted value is still the primary key.
        return $repository->find($id);
    }

    private function isLookupField(ClassMetadata $metadata, string $field): bool
    {
        return $metadata->hasField($field)
            || $metadata->hasAssociation($field)
            || \in_array($field, $metadata->getIdentifierFieldNames(), true);
    }
}
