<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\HttpOperation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicUrlResolver;
use Psr\Log\LoggerInterface;

/**
 * Resolves the Mercure topics API Platform itself publishes for a resource.
 *
 * API Platform publishes the item IRI at UrlGeneratorInterface::ABS_URL, so the auto-resolved item
 * topic is that same absolute IRI template — built through MercureTopicUrlResolver, which shares the
 * routing context with the generator. A relative topic would be resolved by the hub against its own
 * URL and only match while the hub shares the application's origin.
 *
 * Declared plain topics are reused verbatim, which keeps a topic declared in the absolute form API
 * Platform publishes working as the explicit escape hatch. `@=iri(object)` is the one expression the
 * bundle resolves on its own; every other expression topic is dropped with a warning that names it,
 * instead of being silently replaced by the item route path.
 */
class ApiResourceMercureMetadataResolver
{
    /**
     * The only API Platform expression this bundle resolves without the object graph.
     */
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
     * The route path of the item operation API Platform publishes an IRI for.
     *
     * The item IRI `@=iri(object)` expands to is the item GET operation's route, so a GET whose
     * template carries a variable wins even when another operation — a custom mutation route, say —
     * was declared before it and has a variable of its own. Failing a variable-bearing GET, any
     * variable-bearing non-collection operation is preferred over one without, and the first such
     * template is kept as a last resort.
     */
    private function resolveItemPath(ApiResource $resource, string $resourceRoutePrefix): ?string
    {
        $templated = null;
        $fallback  = null;

        foreach ($resource->getOperations() ?? [] as $operation) {
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

            if (!preg_match('/\{[^}]+}/', $path)) {
                $fallback ??= $path;

                continue;
            }

            if ($operation instanceof Get) {
                return $path;
            }

            $templated ??= $path;
        }

        return $templated ?? $fallback;
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
