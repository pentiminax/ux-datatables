<?php

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;

final class ApiResourceCollectionUrlResolver implements ApiResourceCollectionUrlResolverInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {
    }

    public function resolveCollectionUrl(string $entityClass): ?string
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

                if (str_ends_with($path, '{._format}') || str_ends_with($path, '.{_format}')) {
                    $path = substr($path, 0, -10);
                }

                if (preg_match('/\{[^}]+}/', $path)) {
                    continue;
                }

                if ('' === $path) {
                    continue;
                }

                return $path;
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
