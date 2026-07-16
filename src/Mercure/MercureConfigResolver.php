<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

final class MercureConfigResolver implements MercureConfigResolverInterface
{
    public function __construct(
        private readonly MercureHubUrlResolverInterface $hubUrlResolver,
        private readonly ?ApiResourceMercureMetadataResolverInterface $apiResourceMercureMetadataResolver = null,
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
