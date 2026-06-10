<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Enum;

enum ActionsPosition: string
{
    case BeforeColumns = 'before';
    case AfterColumns  = 'after';
}
