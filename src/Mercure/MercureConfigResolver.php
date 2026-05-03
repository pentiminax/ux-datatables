<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Contracts\ApiResourceMercureMetadataResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureHubUrlResolverInterface;
use Symfony\Component\String\Inflector\EnglishInflector;

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
        $resourceName = $this->extractShortName($entityClass);
        $slug         = strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $resourceName));

        return '/datatables/'.$this->pluralize($slug).'/{id}';
    }

    private function extractShortName(string $entityClass): string
    {
        $parts = explode('\\', $entityClass);

        return end($parts) ?: $entityClass;
    }

    private function pluralize(string $value): string
    {
        return (new EnglishInflector())->pluralize($value)[0];
    }
}
