<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolverInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
        private readonly ?MercureTopicResolverInterface $mercureTopicResolver = null,
    ) {
    }

    /**
     * @throws EntityNotFoundException
     */
    public function delete(string $entityClass, int|string $id, ?string $dataTableClass = null): void
    {
        $context = $this->locator->locate($entityClass, $id);

        $context->manager->remove($context->entity);
        $context->manager->flush();

        $this->publisher->publish($this->resolveTopics($entityClass, $dataTableClass), [
            'type' => 'delete',
            'id'   => $id,
        ]);
    }

    /**
     * Writes a boolean field on the entity (inline toggle use case).
     *
     * @throws EntityNotFoundException
     * @throws PropertyNotWritableException
     */
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value, ?string $dataTableClass = null): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->propertyAccessor->isWritable($context->entity, $field)) {
            throw new PropertyNotWritableException($field);
        }

        $this->propertyAccessor->setValue($context->entity, $field, $value);
        $context->manager->flush();

        $this->publisher->publish($this->resolveTopics($entityClass, $dataTableClass), [
            'type'  => 'edit',
            'id'    => $id,
            'field' => $field,
        ]);
    }

    /**
     * @return string[]
     */
    private function resolveTopics(string $entityClass, ?string $dataTableClass): array
    {
        return $this->mercureTopicResolver?->resolve($entityClass, $dataTableClass) ?? [];
    }
}
