<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

/**
 * CSS styling framework DataTables.net renders against.
 *
 * Values match the frontend's `StyleFramework` union in `assets/src/types/styleFramework.ts`;
 * changing a case value here must be mirrored there.
 */
enum StyleFramework: string
{
    case DataTables = 'dt';
    /**
     * @deprecated since 1.5, use Bootstrap5 or DataTables instead. Removed in 2.0.
     */
    case Bootstrap = 'bs';

    /**
     * @deprecated since 1.5, use Bootstrap5 or DataTables instead. Removed in 2.0.
     */
    case Bootstrap4 = 'bs4';

    case Bootstrap5 = 'bs5';
}
