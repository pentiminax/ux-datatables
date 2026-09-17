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

        return new MercureConfig(
            topics: $topics,
            hubUrl: $hubUrl,
            protocolVersion: $this->hubUrlResolver->resolveProtocolVersion(),
        );
    }

    /**
     * Deliberately relative, unlike the API Platform item topic. Nobody but the bundle publishes to
     * this topic, and it publishes through this same resolver, so keeping it context-free makes the
     * two sides agree wherever they run — a web request, a console command, a Messenger consumer.
     * An absolute form would pin both to one routing context and silently stop matching across two.
     */
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
