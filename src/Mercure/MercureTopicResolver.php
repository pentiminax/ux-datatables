<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

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
     * @var array<class-string, true>
     */
    private array $warnedPublishers = [];

    /**
     * @param ?ContainerInterface $dataTables service locator of the registered `datatables.data_table` services
     */
    public function __construct(
        private readonly ?MercureConfigResolver $configResolver = null,
        private readonly ?ContainerInterface $dataTables = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function resolve(string $entityClass, ?string $dataTableClass = null): array
    {
        return $this->resolveConfig($entityClass, $dataTableClass)?->topics ?? [];
    }

    public function warnPublishedPublicly(object $publisher): void
    {
        if (isset($this->warnedPublishers[$publisher::class])) {
            return;
        }

        $this->warnedPublishers[$publisher::class] = true;

        $this->logger?->warning('The Mercure publisher "{publisher}" does not implement PrivateUpdatePublisherInterface, so updates for a table subscribed with credentials are published publicly.', [
            'publisher' => $publisher::class,
        ]);
    }

    public function resolveConfig(string $entityClass, ?string $dataTableClass = null): ?MercureConfig
    {
        if (null !== $dataTableClass && null !== $this->dataTables && $this->dataTables->has($dataTableClass)) {
            $dataTable = $this->dataTables->get($dataTableClass);

            if ($dataTable instanceof AbstractDataTable && $dataTable->getEntityClass() === $entityClass) {
                try {
                    // Resolve the topics the render path serialized to the browser
                    // WITHOUT hydrating client-side data: no data-provider / DB
                    // query is triggered as a side effect of the mutation.
                    $config = $dataTable->resolveMercureConfigWithoutHydration();
                } catch (\Throwable) {
                    // Topic resolution must never fail a mutation that has already
                    // committed (e.g. an unresolvable Mercure hub URL throws a
                    // LogicException). Fall through to the bare entity-class resolver.
                    $config = null;
                }

                if (null !== $config) {
                    return $config;
                }
            }
        }

        return $this->configResolver?->resolveMercureConfig($entityClass);
    }
}
