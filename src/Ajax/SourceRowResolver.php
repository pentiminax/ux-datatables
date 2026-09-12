<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Ajax;

use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformItemResolver;

final class SourceRowResolver
{
    public function __construct(
        private readonly ?ApiPlatformItemResolver $itemResolver = null,
    ) {
    }

    /**
     * Rehydrate the source entity backing each client-supplied row.
     *
     * The returned array preserves the keys of $rows: each entry holds the resolved
     * entity, or null when API Platform does not serve it to the current user. Without
     * API Platform there is no authorization boundary to rehydrate through, so every
     * row stays unresolved rather than being loaded unscoped.
     *
     * @param array<array-key, mixed> $rows
     *
     * @return array<array-key, object|null>
     */
    public function resolve(?string $entityClass, array $rows): array
    {
        $resolved = array_fill_keys(array_keys($rows), null);

        if (null === $entityClass || null === $this->itemResolver) {
            return $resolved;
        }

        foreach ($rows as $key => $row) {
            if (\is_array($row)) {
                $resolved[$key] = $this->itemResolver->resolve($entityClass, $row);
            }
        }

        return $resolved;
    }
}
