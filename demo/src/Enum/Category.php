<?php

declare(strict_types=1);

namespace App\Enum;

enum Category: string
{
    case Audio   = 'audio';
    case Home    = 'home';
    case Office  = 'office';
    case Outdoor = 'outdoor';
    case Kitchen = 'kitchen';
    case Travel  = 'travel';
}
