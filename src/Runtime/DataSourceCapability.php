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
 * 1.6 deprecates keep answering until 2.0 removes them.
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

        return $provider instanceof ScopedIdentifierProviderInterface ? $provider : null;
    }

    public static function identifierCollector(?DataProviderInterface $provider): IdentifierDataProviderInterface|IdentifierCollectingDataProviderInterface|null
    {
        if ($provider instanceof IdentifierDataProviderInterface && self::declares($provider, DataSourceFeature::IdentifierCollection)) {
            return $provider;
        }

        return $provider instanceof IdentifierCollectingDataProviderInterface ? $provider : null;
    }

    /**
     * A provider implementing the merged interface without declaring features is trusted to cover
     * both, since it chose to implement it. A feature-aware provider is asked, and one whose
     * exhaustive match in supports() does not know the cases 1.6 added is treated as unsupported.
     */
    private static function declares(object $provider, DataSourceFeature $feature): bool
    {
        if (!$provider instanceof FeatureAwareDataProviderInterface) {
            return true;
        }

        try {
            return $provider->supports($feature);
        } catch (\UnhandledMatchError) {
            return false;
        }
    }
}
