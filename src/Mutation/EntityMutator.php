<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
        private readonly AuthorizationChecker $permissionChecker,
        private readonly MercureTopicResolver $topicResolver,
        private readonly MutationFlusher $flusher,
    ) {
    }

    /**
     * @throws EntityNotFoundException
     * @throws MutationNotAllowedException
     * @throws MutationPersistenceException
     */
    public function delete(string $entityClass, int|string $id, string $dataTableClass, Action $action): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->isGranted(Permission::DT_DELETE_ROW, $context->entity)) {
            throw new MutationNotAllowedException();
        }

        if (!$this->permissionChecker->isGranted(Permission::DT_EXECUTE_ACTION, new ActionPermissionContext(
            $dataTableClass,
            $action,
            $context->entity,
            true,
        ))) {
            throw new MutationNotAllowedException();
        }

        $context->manager->remove($context->entity);
        $this->flusher->flush($context->manager);

        $this->publisher->publish($this->topicResolver->resolve($entityClass, $dataTableClass), [
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
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value, string $dataTableClass): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->isGranted(Permission::DT_EDIT_ROW, $context->entity)) {
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
        $this->flusher->flush($context->manager);

        $this->publisher->publish($this->topicResolver->resolve($entityClass, $dataTableClass), [
            'type'  => 'edit',
            'id'    => $id,
            'field' => $field,
        ]);
    }
}
