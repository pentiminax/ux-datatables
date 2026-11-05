<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationNotAllowedException;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MutationUpdatePublisher;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
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
     *
     * @deprecated since 1.5, use deleteRow() instead. Removed in 2.0.
     */
    public function delete(string $entityClass, int|string $id, string $dataTableClass, Action $action): void
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.5', 'The "%s()" method is deprecated, use "deleteRow()" instead.', __METHOD__);

        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->canDeleteRow($context->entity)) {
            throw new MutationNotAllowedException();
        }

        if (!$this->permissionChecker->canExecuteActionOnRow($dataTableClass, $action, $context->entity)) {
            throw new MutationNotAllowedException();
        }

        $this->removeAndPublish($context, $entityClass, $dataTableClass, $id);
    }

    /**
     * Removes a row the {@see RowActionGate} has authorized.
     *
     * @internal
     *
     * @throws MutationPersistenceException
     */
    public function deleteRow(AuthorizedRow $row): void
    {
        $this->removeAndPublish($row->context, $row->table->requireEntityClass(), $row->table->dataTableClass, $row->id);
    }

    /**
     * Writes a boolean field on the entity (inline toggle use case).
     *
     * @throws EntityNotFoundException
     * @throws FieldNotToggleableException
     * @throws MutationNotAllowedException
     * @throws MutationPersistenceException
     * @throws PropertyNotWritableException
     *
     * @deprecated since 1.5, use toggleRow() instead. Removed in 2.0.
     */
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value, string $dataTableClass): void
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.5', 'The "%s()" method is deprecated, use "toggleRow()" instead.', __METHOD__);

        $context = $this->locator->locate($entityClass, $id);

        if (!$this->permissionChecker->canEditRow($context->entity)) {
            throw new MutationNotAllowedException();
        }

        $this->writeBoolean($context, $entityClass, $dataTableClass, $id, $field, $value);
    }

    /**
     * Writes the switch field of a row the {@see RowActionGate} has authorized.
     *
     * @internal
     *
     * @throws FieldNotToggleableException
     * @throws MutationPersistenceException
     * @throws PropertyNotWritableException
     */
    public function toggleRow(AuthorizedRow $row, bool $value): void
    {
        $this->writeBoolean($row->context, (string) $row->entityClass, $row->table->dataTableClass, $row->id, (string) $row->field, $value);
    }

    private function removeAndPublish(MutationContext $context, string $entityClass, string $dataTableClass, int|string $id): void
    {
        $context->manager->remove($context->entity);
        $this->flusher->flush($context->manager);

        MutationUpdatePublisher::publish($this->publisher, $this->topicResolver, $entityClass, $dataTableClass, [
            'type' => 'delete',
            'id'   => (string) $id,
        ]);
    }

    private function writeBoolean(MutationContext $context, string $entityClass, string $dataTableClass, int|string $id, string $field, bool $value): void
    {
        $metadata = $context->manager->getClassMetadata($entityClass);

        if (!$metadata->hasField($field) || 'boolean' !== $metadata->getTypeOfField($field)) {
            throw new FieldNotToggleableException($field);
        }

        if (!$this->propertyAccessor->isWritable($context->entity, $field)) {
            throw new PropertyNotWritableException($field);
        }

        $this->propertyAccessor->setValue($context->entity, $field, $value);
        $this->flusher->flush($context->manager);

        MutationUpdatePublisher::publish($this->publisher, $this->topicResolver, $entityClass, $dataTableClass, [
            'type'  => 'edit',
            'id'    => (string) $id,
            'field' => $field,
        ]);
    }
}
