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
 * provider that does not implement this interface is assumed to support every query feature, which
 * 2.0 no longer allows. Row scope and identifier collection are not query features: they follow
 * {@see IdentifierDataProviderInterface}, whose implementer is trusted to cover both unless it
 * declares otherwise here.
 *
 * A supports() that throws an \UnhandledMatchError, such as an exhaustive match lacking a case,
 * triggers a deprecation naming the provider. For a query feature the bundle then refuses the
 * request; for row scope and identifier collection it keeps using IdentifierDataProviderInterface,
 * so the scope check still runs. Answer every case explicitly, with false for features you do not apply.
 */
interface FeatureAwareDataProviderInterface
{
    public function supports(DataSourceFeature $feature): bool;
}
