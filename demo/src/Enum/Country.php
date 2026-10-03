<?php

declare(strict_types=1);

namespace App\Enum;

enum Country: string
{
    case Brazil  = 'BR';
    case Canada  = 'CA';
    case France  = 'FR';
    case Germany = 'DE';
    case Italy   = 'IT';
    case Japan   = 'JP';
    case Mexico  = 'MX';
    case Spain   = 'ES';
}
