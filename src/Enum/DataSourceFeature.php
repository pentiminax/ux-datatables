<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

/**
 * A query feature a data source can refuse; see {@see \Pentiminax\UX\DataTables\Contracts\FeatureAwareDataProviderInterface}.
 */
enum DataSourceFeature: string
{
    case SearchBuilder     = 'searchBuilder';
    case ColumnControl     = 'columnControl';
    case ConfiguredFilters = 'configuredFilters';
    case Summaries         = 'summaries';

    public function label(): string
    {
        return match ($this) {
            self::SearchBuilder     => 'SearchBuilder criteria',
            self::ColumnControl     => 'ColumnControl searches',
            self::ConfiguredFilters => 'Configured filters',
            self::Summaries         => 'Column summaries',
        };
    }
}
