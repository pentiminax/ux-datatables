<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;

class ApiResourceMercureMetadataResolver
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
     * Whether API Platform marks this resource private, at the resource level or on any operation.
     *
     * Any declaration wins: the bundle cannot know which operation published a given update, so it
     * over-approximates towards sending credentials rather than towards a silent subscription.
     */
    public function resolvePrivate(string $entityClass): bool
    {
        try {
            $collection = $this->resourceMetadataFactory->create($entityClass);
        } catch (\Throwable) {
            // Mirrors resolveTopics(): the factory chain throws on a class it cannot resolve
            // (ResourceClassNotFoundException) and on a handful of resource misconfigurations,
            // and every entity-backed table reaches this method through MercureConfigResolver.
            // Such a table falls back to the bundle's internal topic and is not private, so the
            // failure must not take the render down with it. The exception classes span
            // api-platform/core 3 and 4, where several moved namespace, hence \Throwable.
            return false;
        }

        foreach ($collection as $resource) {
            if (true === $this->normalizePrivate($resource->getMercure())) {
                return true;
            }

            foreach ($resource->getOperations() ?? [] as $operation) {
                if (true === $this->normalizePrivate($operation->getMercure())) {
                    return true;
                }
            }
        }

        return false;
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

    private function normalizePrivate(mixed $mercure): bool
    {
        if (!\is_array($mercure)) {
            return false;
        }

        return true === ($mercure['private'] ?? false);
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
