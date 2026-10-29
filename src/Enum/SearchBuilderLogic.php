<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum SearchBuilderLogic: string
{
    case And = 'AND';
    case Or  = 'OR';
}
