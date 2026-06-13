<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

enum TestStatusWithLabel: string
{
    case Active   = 'active';
    case Inactive = 'inactive';

    public function getLabel(): string
    {
        return match ($this) {
            self::Active   => 'Active ✅',
            self::Inactive => 'Inactive ❌',
        };
    }
}
