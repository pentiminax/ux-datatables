<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

/**
 * Resolves the authoritative Mercure topics for a mutated entity, server-side.
 *
 * Shared by EntityMutator and EditFormService so that delete/edit/edit-form
 * mutations never trust client-supplied topics. When the mutation originates
 * from a known DataTable, the topics are derived from that DataTable's fully
 * resolved Mercure configuration (manual, attribute, or auto-resolved) —
 * exactly what the render path serialized to the browser — so a live update
 * always publishes to the topics the client actually subscribed to. Falls
 * back to the bare entity-class resolver when no DataTable can be resolved.
 */
final class MercureTopicResolver
{
    /**
     * @return string[]
     */
    public static function resolve(
        ?MercureConfigResolverInterface $resolver,
        string $entityClass,
        ?ContainerInterface $dataTables = null,
        ?string $dataTableClass = null,
    ): array {
        if (null !== $dataTableClass && null !== $dataTables && $dataTables->has($dataTableClass)) {
            $dataTable = $dataTables->get($dataTableClass);

            if ($dataTable instanceof AbstractDataTable && $dataTable->getEntityClass() === $entityClass) {
                $topics = $dataTable->getDataTable()->getMercureConfig()?->topics;

                if (null !== $topics) {
                    return $topics;
                }
            }
        }

        return $resolver?->resolveMercureConfig($entityClass)?->topics ?? [];
    }
}
