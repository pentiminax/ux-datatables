<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Ajax\AuthorizedRow;
use Pentiminax\UX\DataTables\Ajax\RowActionGate;
use Pentiminax\UX\DataTables\Contracts\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Exception\FieldNotToggleableException;
use Pentiminax\UX\DataTables\Exception\MutationPersistenceException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MutationUpdatePublisher;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
        private readonly MercureTopicResolver $topicResolver,
        private readonly MutationFlusher $flusher,
    ) {
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
