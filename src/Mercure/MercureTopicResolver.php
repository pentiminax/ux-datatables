<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Psr\Container\ContainerInterface;

/**
 * Resolves the authoritative Mercure topics for a mutation (delete, boolean
 * toggle, edit-form submit) server-side, mirroring the priority order used by
 * the render path (RenderingPreparer::configureMercure): manual DataTable
 * configuration, then the explicit AsDataTable attribute, then the
 * entity-based resolver fallback.
 *
 * Topics are never taken from the client request.
 */
final class MercureTopicResolver implements MercureTopicResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $dataTables,
        private readonly ?MercureConfigResolverInterface $mercureConfigResolver = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function resolve(string $entityClass, ?string $dataTableClass = null): array
    {
        $topics = $this->resolveFromDataTable($dataTableClass);

        if (null !== $topics) {
            return $topics;
        }

        return $this->mercureConfigResolver?->resolveMercureConfig($entityClass)?->topics ?? [];
    }

    /**
     * @return string[]|null
     */
    private function resolveFromDataTable(?string $dataTableClass): ?array
    {
        if (null === $dataTableClass || !$this->dataTables->has($dataTableClass)) {
            return null;
        }

        $dataTable = $this->dataTables->get($dataTableClass);

        if (!$dataTable instanceof AbstractDataTable) {
            return null;
        }

        $manualConfig = $dataTable->getConfiguredDataTable()->getMercureConfig();
        if (null !== $manualConfig) {
            return $manualConfig->topics;
        }

        return $this->resolveFromAttribute($dataTable);
    }

    /**
     * @return string[]|null
     */
    private function resolveFromAttribute(AbstractDataTable $dataTable): ?array
    {
        $asDataTable = $dataTable->getAsDataTableAttribute();

        if (null === $asDataTable || !\is_array($asDataTable->mercure)) {
            return null;
        }

        $topics = $asDataTable->mercure['topics'] ?? [];

        if (\is_string($topics)) {
            $topics = [$topics];
        }

        if (!\is_array($topics) || [] === $topics) {
            return null;
        }

        return $topics;
    }
}
