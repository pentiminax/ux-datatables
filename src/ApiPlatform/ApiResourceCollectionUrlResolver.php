<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;

class ApiResourceCollectionUrlResolver
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {
    }

    public function resolveCollectionUrl(string $entityClass): ?string
    {
        return $this->resolveCollection($entityClass)?->url;
    }

    public function resolveCollectionOperation(string $entityClass): ?Operation
    {
        return $this->resolveCollection($entityClass)?->operation;
    }

    /**
     * The path and the operation come from one walk of the resource metadata. Server-side rendering
     * reads rows through this operation while the browser may query the same path, so both must
     * describe the same choice: a table whose browser requests and whose server requests target
     * different collection operations would show rows filtered, scoped or secured differently from
     * the ones it counts.
     */
    public function resolveCollection(string $entityClass): ?ResolvedCollectionOperation
    {
        try {
            $collection = $this->resourceMetadataFactory->create($entityClass);
        } catch (\Throwable) {
            return null;
        }

        foreach ($collection as $resource) {
            $resourceRoutePrefix = $resource->getRoutePrefix() ?? '/api';

            foreach ($resource->getOperations() ?? [] as $operation) {
                if (!$operation instanceof CollectionOperationInterface) {
                    continue;
                }

                $uriTemplate = $operation->getUriTemplate();
                $routePrefix = $operation->getRoutePrefix() ?? $resourceRoutePrefix;
                $path        = $this->buildPath($routePrefix, $uriTemplate);

                foreach (['{._format}', '.{_format}'] as $suffix) {
                    if (str_ends_with($path, $suffix)) {
                        $path = substr($path, 0, -\strlen($suffix));
                        break;
                    }
                }

                if (preg_match('/\{[^}]+}/', $path)) {
                    continue;
                }

                if ('' === $path) {
                    continue;
                }

                return new ResolvedCollectionOperation($path, $operation);
            }
        }

        return null;
    }

    private function buildPath(?string $routePrefix, string $uriTemplate): string
    {
        if (null === $routePrefix || '' === $routePrefix) {
            return $uriTemplate;
        }

        return rtrim($routePrefix, '/').'/'.ltrim($uriTemplate, '/');
    }
}
