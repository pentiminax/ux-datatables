<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

enum TestStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Pending  = 'pending';
}
