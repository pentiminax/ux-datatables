<?php

declare(strict_types=1);

namespace App\Enum;

enum ProductStatus: string
{
    case Active   = 'active';
    case Draft    = 'draft';
    case Archived = 'archived';
}
