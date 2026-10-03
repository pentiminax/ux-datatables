<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum Category: string implements TranslatableInterface
{
    case Audio   = 'audio';
    case Home    = 'home';
    case Office  = 'office';
    case Outdoor = 'outdoor';
    case Kitchen = 'kitchen';
    case Travel  = 'travel';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.category.'.$this->value, [], null, $locale);
    }
}
