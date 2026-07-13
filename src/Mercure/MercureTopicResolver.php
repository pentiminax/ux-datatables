<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

/**
 * Resolves the authoritative Mercure topics for a mutated entity, server-side.
 *
 * Shared by EntityMutator and EditFormService so that delete/edit/edit-form
 * mutations never trust client-supplied topics: they are always derived from
 * the entity configuration through the same resolver used by the render path.
 */
final class MercureTopicResolver
{
    /**
     * @return string[]
     */
    public static function resolve(?MercureConfigResolverInterface $resolver, string $entityClass): array
    {
        return $resolver?->resolveMercureConfig($entityClass)?->topics ?? [];
    }
}
