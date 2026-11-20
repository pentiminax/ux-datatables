<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Mercure;

use Pentiminax\UX\DataTables\Runtime\DataSourceMode;
use Pentiminax\UX\DataTables\Runtime\ResolvedOptIns;

/**
 * Resolves the Mercure configuration a table serializes to the browser.
 *
 * Single source of truth for the topic precedence — manual ->mercure() > explicit
 * #[AsDataTable(mercure: [...])] > entity-class auto-resolver — shared by the render path and the
 * publish path, so a mutation always publishes to the topics the client subscribed to.
 *
 * Both collaborators are optional: without Mercure installed, only a table that asks for Mercure
 * explicitly fails, because it cannot resolve a hub URL.
 *
 * @internal
 */
final class MercureTableConfigResolver
{
    public function __construct(
        private readonly ?MercureConfigResolver $configResolver = null,
        private readonly ?MercureHubUrlResolver $hubUrlResolver = null,
    ) {
    }

    /**
     * Returns null when the table exposes no live Mercure config.
     *
     * @throws \LogicException           if Mercure is enabled but the hub URL cannot be resolved
     * @throws \InvalidArgumentException if the attribute declares invalid Mercure options
     */
    public function resolve(ResolvedOptIns $optIns): ?MercureConfig
    {
        $mercure = $optIns->mercure;

        if ($mercure instanceof MercureConfig) {
            return $this->withHub($mercure);
        }

        if (false === $mercure || null === $optIns->entityClass) {
            return null;
        }

        // Embedded rows never refresh, so only a manual ->mercure() config subscribes them.
        if (\in_array($optIns->dataSourceMode, [DataSourceMode::Inline, DataSourceMode::ClientHydrated], true)) {
            return null;
        }

        if (\is_array($mercure)) {
            return $this->fromAttribute($mercure, $optIns->entityClass);
        }

        return $this->configResolver?->resolveMercureConfig($optIns->entityClass);
    }

    /**
     * Without a `topics` key the array only carries subscription options, so topics come from the
     * auto-resolver and the declared options are applied on top of it.
     *
     * @param array<string, mixed> $mercure
     */
    private function fromAttribute(array $mercure, string $entityClass): ?MercureConfig
    {
        $unknown = array_diff(array_keys($mercure), ['topics', 'withCredentials', 'debounceMs']);
        if ([] !== $unknown) {
            throw new \InvalidArgumentException(\sprintf('Unknown mercure option(s) "%s" declared on #[AsDataTable] for "%s". Supported options are "topics", "withCredentials" and "debounceMs".', implode('", "', $unknown), $entityClass));
        }

        $debounceMs = $mercure['debounceMs'] ?? null;
        if (null !== $debounceMs && !\is_int($debounceMs)) {
            throw new \InvalidArgumentException(\sprintf('The mercure "debounceMs" option declared on #[AsDataTable] for "%s" must be an integer or null.', $entityClass));
        }

        $withCredentials = $mercure['withCredentials'] ?? null;
        if (null !== $withCredentials && !\is_bool($withCredentials)) {
            throw new \InvalidArgumentException(\sprintf('The mercure "withCredentials" option declared on #[AsDataTable] for "%s" must be a boolean.', $entityClass));
        }

        if (!\array_key_exists('topics', $mercure)) {
            $resolved = $this->configResolver?->resolveMercureConfig($entityClass);

            if (null === $resolved) {
                return null;
            }

            return new MercureConfig(
                topics: $resolved->topics,
                withCredentials: $withCredentials ?? $resolved->withCredentials,
                debounceMs: $debounceMs,
                hubUrl: $resolved->hubUrl,
                protocolVersion: $resolved->protocolVersion,
            );
        }

        $topics = $mercure['topics'];
        if (\is_string($topics)) {
            $topics = [$topics];
        }

        if (!\is_array($topics)) {
            throw new \InvalidArgumentException(\sprintf('The mercure "topics" option declared on #[AsDataTable] for "%s" must be a string or an array of strings.', $entityClass));
        }

        return $this->withHub(new MercureConfig(
            topics: $topics,
            withCredentials: $withCredentials ?? false,
            debounceMs: $debounceMs,
        ));
    }

    private function withHub(MercureConfig $config): MercureConfig
    {
        $hubUrl = $this->hubUrlResolver?->resolveHubUrl();

        if (null === $hubUrl || '' === $hubUrl) {
            throw new \LogicException('Cannot enable Mercure on this DataTable: the Mercure hub URL could not be resolved. Ensure symfony/mercure-bundle is installed and configured (e.g. MERCURE_URL / MERCURE_PUBLIC_URL).');
        }

        return $config
            ->withHubUrl($hubUrl)
            ->withProtocolVersion($this->hubUrlResolver->resolveProtocolVersion());
    }
}
