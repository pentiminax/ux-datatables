<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ActionType: string
{
    case Delete = 'DELETE';
    case Detail = 'DETAIL';
    case Edit   = 'EDIT';
}
