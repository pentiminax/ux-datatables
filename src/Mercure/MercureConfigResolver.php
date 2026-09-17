<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;

class MercureConfigResolver
{
    public function __construct(
        private readonly MercureHubUrlResolver $hubUrlResolver,
        private readonly ?ApiResourceMercureMetadataResolver $apiResourceMercureMetadataResolver = null,
    ) {
    }

    public function resolveMercureConfig(string $entityClass): ?MercureConfig
    {
        $hubUrl = $this->hubUrlResolver->resolveHubUrl();
        if (null === $hubUrl) {
            return null;
        }

        $topics = $this->apiResourceMercureMetadataResolver?->resolveTopics($entityClass) ?? [];

        if ([] === $topics) {
            $topics = [$this->buildFallbackTopic($entityClass)];
        }

        // API Platform publishes a `private` update only to a subscriber whose token grants one of
        // the update's topics, so a resource marked private has to open its EventSource with
        // credentials. An explicit `withCredentials` never reaches this resolver: the manual
        // `->mercure()` and `#[AsDataTable(mercure: [...])]` paths are resolved before it.
        $withCredentials = $this->apiResourceMercureMetadataResolver?->resolvePrivate($entityClass) ?? false;

        return new MercureConfig(
            topics: $topics,
            withCredentials: $withCredentials,
            hubUrl: $hubUrl,
            protocolVersion: $this->hubUrlResolver->resolveProtocolVersion(),
        );
    }

    private function buildFallbackTopic(string $entityClass): string
    {
        return MercureTopicFactory::fallbackTopic($this->extractShortName($entityClass));
    }

    private function extractShortName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);

        return end($parts) ?: $entityClass;
    }
}
