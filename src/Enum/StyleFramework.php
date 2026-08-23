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
    case Bootstrap  = 'bs';
    case Bootstrap4 = 'bs4';
    case Bootstrap5 = 'bs5';
    case Foundation = 'zf';
    case JQueryUI   = 'jqui';
    case SemanticUI = 'se';
}
