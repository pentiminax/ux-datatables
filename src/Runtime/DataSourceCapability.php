<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierDataProviderInterface;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;

/**
 * Answers whether a data provider can check the row scope or collect identifiers.
 *
 * A provider declares either through supports() and implements IdentifierDataProviderInterface.
 *
 * @internal
 */
final class DataSourceCapability
{
    public static function rowScopeChecker(?DataProviderInterface $provider): ?IdentifierDataProviderInterface
    {
        return self::identifierProvider($provider, DataSourceFeature::RowScope);
    }

    public static function identifierCollector(?DataProviderInterface $provider): ?IdentifierDataProviderInterface
    {
        return self::identifierProvider($provider, DataSourceFeature::IdentifierCollection);
    }

    /**
     * Fail closed: a feature the provider's supports() does not know, such as a case missing from
     * its exhaustive match, counts as unsupported.
     */
    public static function supports(DataProviderInterface $provider, DataSourceFeature $feature): bool
    {
        try {
            return $provider->supports($feature);
        } catch (\UnhandledMatchError) {
            return false;
        }
    }

    private static function identifierProvider(?DataProviderInterface $provider, DataSourceFeature $feature): ?IdentifierDataProviderInterface
    {
        if ($provider instanceof IdentifierDataProviderInterface && self::supports($provider, $feature)) {
            return $provider;
        }

        return null;
    }
}
