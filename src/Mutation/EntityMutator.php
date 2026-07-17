<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Doctrine\DBAL\Exception as DBALException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\Persistence\ObjectManager;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Psr\Container\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
        private readonly PermissionChecker $permissionChecker,
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
        private readonly ?ContainerInterface $dataTables = null,
    ) {
    }

    /**
     * @throws EntityNotFoundException
     * @throws MutationNotAllowedException
     * @throws MutationPersistenceException
     */
    public function delete(string $entityClass, int|string $id, ?string $dataTableClass = null): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->isGranted('DELETE', $context->entity)) {
            throw new MutationNotAllowedException();
        }

        $context->manager->remove($context->entity);
        $this->flush($context->manager);

        $this->publisher->publish(MercureTopicResolver::resolve($this->mercureConfigResolver, $entityClass, $this->dataTables, $dataTableClass), [
            'type' => 'delete',
            'id'   => $id,
        ]);
    }

    /**
     * Writes a boolean field on the entity (inline toggle use case).
     *
     * @throws EntityNotFoundException
     * @throws FieldNotToggleableException
     * @throws MutationNotAllowedException
     * @throws MutationPersistenceException
     * @throws PropertyNotWritableException
     */
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value, ?string $dataTableClass = null): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->isGranted('EDIT', $context->entity)) {
            throw new MutationNotAllowedException();
        }

        $metadata = $context->manager->getClassMetadata($entityClass);

        if (!$metadata->hasField($field) || 'boolean' !== $metadata->getTypeOfField($field)) {
            throw new FieldNotToggleableException($field);
        }

        if (!$this->propertyAccessor->isWritable($context->entity, $field)) {
            throw new PropertyNotWritableException($field);
        }

        $this->propertyAccessor->setValue($context->entity, $field, $value);
        $this->flush($context->manager);

        $this->publisher->publish(MercureTopicResolver::resolve($this->mercureConfigResolver, $entityClass, $this->dataTables, $dataTableClass), [
            'type'  => 'edit',
            'id'    => $id,
            'field' => $field,
        ]);
    }

    /**
     * @throws MutationPersistenceException when the underlying persistence layer rejects the flush
     */
    private function flush(ObjectManager $manager): void
    {
        try {
            $manager->flush();
        } catch (DBALException|OptimisticLockException $exception) {
            // The 409 primarily targets constraint/conflict cases — a unique
            // violation or an optimistic-lock version mismatch. Broader DBAL
            // failures (e.g. a lost connection) are deliberately mapped here
            // too rather than leaking as a raw 500.
            throw new MutationPersistenceException(previous: $exception);
        }
    }
}
