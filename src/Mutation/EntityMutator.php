<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
        private readonly PermissionChecker $permissionChecker = new PermissionChecker(),
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
    ) {
    }

    /**
     * @throws EntityNotFoundException
     * @throws MutationNotAllowedException
     */
    public function delete(string $entityClass, int|string $id): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->isGranted('DELETE', $context->entity)) {
            throw new MutationNotAllowedException();
        }

        $context->manager->remove($context->entity);
        $context->manager->flush();

        $this->publisher->publish(MercureTopicResolver::resolve($this->mercureConfigResolver, $entityClass), [
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
     * @throws PropertyNotWritableException
     */
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value): void
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
        $context->manager->flush();

        $this->publisher->publish(MercureTopicResolver::resolve($this->mercureConfigResolver, $entityClass), [
            'type'  => 'edit',
            'id'    => $id,
            'field' => $field,
        ]);
    }
}
