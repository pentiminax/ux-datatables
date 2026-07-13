<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mutation;

use Pentiminax\UX\DataTables\Exception\EntityNotFoundException;
use Pentiminax\UX\DataTables\Exception\PropertyNotWritableException;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

final class EntityMutator
{
    public function __construct(
        private readonly EntityLocator $locator,
        private readonly PropertyAccessorInterface $propertyAccessor,
        private readonly MercurePublisherInterface $publisher,
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
    ) {
    }

    /**
     * @throws EntityNotFoundException
     */
    public function delete(string $entityClass, int|string $id): void
    {
        $context = $this->locator->locate($entityClass, $id);

        $context->manager->remove($context->entity);
        $context->manager->flush();

        $this->publisher->publish($this->resolveTopics($entityClass), [
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
    public function setProperty(string $entityClass, int|string $id, string $field, bool $value): void
    {
        $context = $this->locator->locate($entityClass, $id);

        if (!$this->propertyAccessor->isWritable($context->entity, $field)) {
            throw new PropertyNotWritableException($field);
        }

        $this->propertyAccessor->setValue($context->entity, $field, $value);
        $context->manager->flush();

        $this->publisher->publish($this->resolveTopics($entityClass), [
            'type'  => 'edit',
            'id'    => $id,
            'field' => $field,
        ]);
    }

    /**
     * Resolves the authoritative Mercure topics for the target entity server-side.
     *
     * Topics are never taken from the client request: they are derived from the
     * entity configuration through the same resolver used by the render path.
     *
     * @return string[]
     */
    private function resolveTopics(string $entityClass): array
    {
        return $this->mercureConfigResolver?->resolveMercureConfig($entityClass)?->topics ?? [];
    }
}
