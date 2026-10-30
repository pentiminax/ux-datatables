<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\Enum\DataSourceFeature;

/**
 * A provider that declares which data source features it applies.
 *
 * The bundle refuses a request that uses a feature the provider does not support, instead of letting
 * the provider answer unfiltered: SearchBuilder criteria, ColumnControl searches and configured
 * filter values get HTTP 400, and a table that declares summaries raises a \LogicException. A
 * provider that does not implement this interface is assumed to support every feature.
 */
interface FeatureAwareDataProviderInterface
{
    public function supports(DataSourceFeature $feature): bool;
}
