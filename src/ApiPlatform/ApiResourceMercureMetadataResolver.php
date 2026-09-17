<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicUrlResolver;
use Psr\Log\LoggerInterface;

/**
 * Resolves the Mercure topics API Platform publishes for a resource.
 */
class ApiResourceMercureMetadataResolver
{
    private const ITEM_IRI_EXPRESSION = '@=iri(object)';

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly ?MercureTopicUrlResolver $topicUrlResolver = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return string[]
     */
    public function resolveTopics(string $entityClass): array
    {
        try {
            $collection = $this->resourceMetadataFactory->create($entityClass);
        } catch (\Throwable) {
            return [];
        }

        foreach ($collection as $resource) {
            $itemPath = $this->resolveItemPath($resource, $resource->getRoutePrefix() ?? '/api');

            $resourceTopics = $this->resolveDeclaredTopics($resource->getMercure(), $entityClass, $itemPath);
            if ([] !== $resourceTopics) {
                return $resourceTopics;
            }

            foreach ($resource->getOperations() ?? [] as $operation) {
                $operationTopics = $this->resolveDeclaredTopics($operation->getMercure(), $entityClass, $itemPath);
                if ([] !== $operationTopics) {
                    return $operationTopics;
                }
            }

            if (null !== $itemPath) {
                return [$this->absoluteUrl($itemPath)];
            }
        }

        return [];
    }

    /**
     * Mirrors the operation API Platform itself generates the item IRI from, so the subscription
     * cannot name a topic the publisher never uses: `IriConverter::getIriFromResource()` asks for
     * `ResourceMetadataCollection::getOperation(null, false, true)`, which takes the first
     * non-collection operation whose HTTP method is GET, HEAD or OPTIONS, in declaration order.
     *
     * The rule is the method, not the operation class and not the shape of the template: a custom
     * `HttpOperation(method: 'GET')` counts, a variable-less template still wins if it comes first,
     * and a resource without such an operation has no item IRI at all — API Platform throws
     * OperationNotFoundException rather than publishing one, so null is the honest answer.
     */
    private function resolveItemPath(ApiResource $resource, string $resourceRoutePrefix): ?string
    {
        foreach ($resource->getOperations() ?? [] as $operation) {
            if (!$operation instanceof HttpOperation || $operation instanceof CollectionOperationInterface) {
                continue;
            }

            if (!\in_array($operation->getMethod(), ['GET', 'HEAD', 'OPTIONS'], true)) {
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

            return $path;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function resolveDeclaredTopics(mixed $mercure, string $entityClass, ?string $itemPath): array
    {
        $resolvedTopics = [];

        foreach ($this->extractTopics($mercure) as $topic) {
            if (!\is_string($topic) || '' === $topic) {
                continue;
            }

            if (!str_starts_with($topic, '@=')) {
                $resolvedTopics[] = $topic;

                continue;
            }

            if (self::ITEM_IRI_EXPRESSION === trim($topic)) {
                if (null !== $itemPath) {
                    $resolvedTopics[] = $this->absoluteUrl($itemPath);
                } else {
                    $this->logger?->warning(\sprintf('Cannot resolve "%s" for "%s": no item operation was found.', $topic, $entityClass));
                }

                continue;
            }

            $this->logger?->warning(\sprintf('Dropped the Mercure expression topic "%s" of "%s": only "@=iri(object)" can be resolved without the object graph.', $topic, $entityClass));
        }

        return array_values(array_unique($resolvedTopics));
    }

    /**
     * @return list<mixed>
     */
    private function extractTopics(mixed $mercure): array
    {
        if (null === $mercure || false === $mercure || true === $mercure) {
            return [];
        }

        if (\is_string($mercure)) {
            return [$mercure];
        }

        if (!\is_array($mercure)) {
            return [];
        }

        $topics = $mercure['topics'] ?? [];

        if (\is_string($topics)) {
            return [$topics];
        }

        if (!\is_array($topics)) {
            return [];
        }

        return array_values($topics);
    }

    private function absoluteUrl(string $path): string
    {
        return $this->topicUrlResolver?->absoluteUrl($path) ?? $path;
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
