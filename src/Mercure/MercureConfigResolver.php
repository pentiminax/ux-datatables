<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;

class MercureConfigResolver
{
    public function __construct(
        private readonly MercureHubUrlResolver $hubUrlResolver,
        private readonly ?ApiResourceMercureMetadataResolver $apiResourceMercureMetadataResolver = null,
        private readonly ?MercureTopicUrlResolver $topicUrlResolver = null,
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
            $topics = [$this->absoluteTopic($this->buildFallbackTopic($entityClass))];
        }

        return new MercureConfig(
            topics: $topics,
            hubUrl: $hubUrl,
            protocolVersion: $this->hubUrlResolver->resolveProtocolVersion(),
        );
    }

    /**
     * The fallback topic is the one the bundle itself publishes mutations to, so it has to be
     * absolute as well: the hub would otherwise resolve the relative subscription pattern against
     * its own URL and never match the topic that was published.
     */
    private function absoluteTopic(string $path): string
    {
        return $this->topicUrlResolver?->absoluteUrl($path) ?? $path;
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
