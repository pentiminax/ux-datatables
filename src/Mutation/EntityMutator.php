<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
    ) {
    }

    /**
     * @param string|string[] $topics
     *
     * @throws EntityNotFoundException
     */
    public function delete(string $entityClass, int|string $id, string|array $topics = []): void
    {
        $context = $this->locator->locate($entityClass, $id);

        $context->manager->remove($context->entity);
        $context->manager->flush();

        $this->publisher->publish($topics, [
            'type' => 'delete',
            'id'   => $id,
        ]);
    }

    /**
     * Writes a boolean field on the entity (inline toggle use case).
     *
     * @param string|string[] $topics
     *
     * @throws EntityNotFoundException
     * @throws PropertyNotWritableException
     */
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value, string|array $topics = []): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->propertyAccessor->isWritable($context->entity, $field)) {
            throw new PropertyNotWritableException($field);
        }

        $this->propertyAccessor->setValue($context->entity, $field, $value);
        $context->manager->flush();

        $this->publisher->publish($topics, [
            'type'  => 'edit',
            'id'    => $id,
            'field' => $field,
        ]);
    }
}
