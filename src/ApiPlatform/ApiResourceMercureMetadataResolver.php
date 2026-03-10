<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Contracts\ApiResourceMercureMetadataResolverInterface;

final class ApiResourceMercureMetadataResolver implements ApiResourceMercureMetadataResolverInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
    ) {
    }

    public function resolveTopics(string $entityClass): array
    {
        try {
            $collection = $this->resourceMetadataFactory->create($entityClass);
        } catch (\Throwable) {
            return [];
        }

        foreach ($collection as $resource) {
            $resourceTopics = $this->normalizeTopics($resource->getMercure());
            if ([] !== $resourceTopics) {
                return $resourceTopics;
            }

            $resourceRoutePrefix = $resource->getRoutePrefix() ?? '/api';

            foreach ($resource->getOperations() ?? [] as $operation) {
                $operationTopics = $this->normalizeTopics($operation->getMercure());
                if ([] !== $operationTopics) {
                    return $operationTopics;
                }

                if (!$operation instanceof HttpOperation || $operation instanceof CollectionOperationInterface) {
                    continue;
                }

                $uriTemplate = $operation->getUriTemplate();
                if (null === $uriTemplate || '' === $uriTemplate) {
                    continue;
                }

                $routePrefix = $operation->getRoutePrefix() ?? $resourceRoutePrefix;
                $path        = $this->normalizePath($this->buildPath($routePrefix, $uriTemplate));

                if ('' === $path) {
                    continue;
                }

                return [$path];
            }
        }

        return [];
    }

    /**
     * @return string[]
     */
    private function normalizeTopics(mixed $mercure): array
    {
        if (null === $mercure || false === $mercure || true === $mercure) {
            return [];
        }

        if (\is_string($mercure)) {
            return $this->filterTopics([$mercure]);
        }

        if (!\is_array($mercure)) {
            return [];
        }

        $topics = $mercure['topics'] ?? [];

        if (\is_string($topics)) {
            return $this->filterTopics([$topics]);
        }

        if (!\is_array($topics)) {
            return [];
        }

        return $this->filterTopics($topics);
    }

    /**
     * @param list<mixed> $topics
     *
     * @return string[]
     */
    private function filterTopics(array $topics): array
    {
        $resolvedTopics = [];

        foreach ($topics as $topic) {
            if (!\is_string($topic)) {
                continue;
            }

            if (str_starts_with($topic, '@=')) {
                continue;
            }

            $resolvedTopics[] = $topic;
        }

        return array_values(array_unique($resolvedTopics));
    }

    private function buildPath(?string $routePrefix, string $uriTemplate): string
    {
        if (null === $routePrefix || '' === $routePrefix) {
            return $uriTemplate;
        }

        return rtrim($routePrefix, '/').'/'.ltrim($uriTemplate, '/');
    }

    private function normalizePath(string $path): string
    {
        foreach (['{._format}', '.{_format}'] as $suffix) {
            if (str_ends_with($path, $suffix)) {
                return substr($path, 0, -\strlen($suffix));
            }
        }

        return $path;
    }
}
