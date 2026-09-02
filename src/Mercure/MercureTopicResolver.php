<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

/**
 * Resolves the authoritative Mercure topics for a mutated entity, server-side.
 *
 * Injected into EntityMutator and EditFormService so that delete/edit/edit-form
 * mutations never trust client-supplied topics. When the mutation originates
 * from a known DataTable, the topics are derived from that DataTable's fully
 * resolved Mercure configuration (manual, attribute, or auto-resolved) —
 * exactly what the render path serialized to the browser — so a live update
 * always publishes to the topics the client actually subscribed to. Falls
 * back to the bare entity-class resolver when no DataTable can be resolved.
 *
 * Both collaborators are optional: without Mercure installed there is no
 * config resolver, and the service still resolves to an empty topic list so
 * that mutations keep working.
 */
class MercureTopicResolver
{
    /**
     * @param ?ContainerInterface $dataTables service locator of the registered `datatables.data_table` services
     */
    public function __construct(
        private readonly ?MercureConfigResolver $configResolver = null,
        private readonly ?ContainerInterface $dataTables = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function resolve(string $entityClass, ?string $dataTableClass = null): array
    {
        if (null !== $dataTableClass && null !== $this->dataTables && $this->dataTables->has($dataTableClass)) {
            $dataTable = $this->dataTables->get($dataTableClass);

            if ($dataTable instanceof AbstractDataTable && $dataTable->getEntityClass() === $entityClass) {
                try {
                    // Resolve the topics the render path serialized to the browser
                    // WITHOUT hydrating client-side data: no data-provider / DB
                    // query is triggered as a side effect of the mutation.
                    $topics = $dataTable->resolveMercureConfigWithoutHydration()?->topics;
                } catch (\Throwable) {
                    // Topic resolution must never fail a mutation that has already
                    // committed (e.g. an unresolvable Mercure hub URL throws a
                    // LogicException). Fall through to the bare entity-class resolver.
                    $topics = null;
                }

                if (null !== $topics) {
                    return $topics;
                }
            }
        }

        return $this->configResolver?->resolveMercureConfig($entityClass)?->topics ?? [];
    }
}
