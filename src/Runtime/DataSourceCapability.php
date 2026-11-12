<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierCollectingDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\ScopedIdentifierProviderInterface;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;

/**
 * Answers whether a data provider can check the row scope or collect identifiers.
 *
 * A provider declares either through supports() and the merged interface; the two interfaces
 * 1.6 deprecates keep answering until 2.0 removes them. Its supports() is how the bundle asks a
 * provider about a feature, so an unknown feature is unsupported everywhere.
 *
 * @internal
 */
final class DataSourceCapability
{
    public static function rowScopeChecker(?DataProviderInterface $provider): IdentifierDataProviderInterface|ScopedIdentifierProviderInterface|null
    {
        if ($provider instanceof IdentifierDataProviderInterface && self::declares($provider, DataSourceFeature::RowScope)) {
            return $provider;
        }

        if (!$provider instanceof ScopedIdentifierProviderInterface) {
            return null;
        }

        self::deprecateLegacyInterface($provider, ScopedIdentifierProviderInterface::class, DataSourceFeature::RowScope);

        return $provider;
    }

    public static function identifierCollector(?DataProviderInterface $provider): IdentifierDataProviderInterface|IdentifierCollectingDataProviderInterface|null
    {
        if ($provider instanceof IdentifierDataProviderInterface && self::declares($provider, DataSourceFeature::IdentifierCollection)) {
            return $provider;
        }

        if (!$provider instanceof IdentifierCollectingDataProviderInterface) {
            return null;
        }

        self::deprecateLegacyInterface($provider, IdentifierCollectingDataProviderInterface::class, DataSourceFeature::IdentifierCollection);

        return $provider;
    }

    /**
     * Fail closed: a feature the provider's supports() does not know, such as a case missing from
     * its exhaustive match, counts as unsupported. This is permanent policy, not a 1.6 migration aid.
     */
    public static function supports(FeatureAwareDataProviderInterface $provider, DataSourceFeature $feature): bool
    {
        try {
            return $provider->supports($feature);
        } catch (\UnhandledMatchError) {
            return false;
        }
    }

    /**
     * A provider implementing the merged interface without declaring features is trusted to cover
     * both, since it chose to implement it. A feature-aware provider is asked.
     */
    private static function declares(object $provider, DataSourceFeature $feature): bool
    {
        return !$provider instanceof FeatureAwareDataProviderInterface || self::supports($provider, $feature);
    }

    private static function deprecateLegacyInterface(object $provider, string $interface, DataSourceFeature $feature): void
    {
        trigger_deprecation('pentiminax/ux-datatables', '1.6', 'Data provider "%s" answers through "%s", which is removed in 2.0. Implement "%s" and declare "%s::%s" in supports() instead.', $provider::class, $interface, IdentifierDataProviderInterface::class, DataSourceFeature::class, $feature->name);
    }
}
