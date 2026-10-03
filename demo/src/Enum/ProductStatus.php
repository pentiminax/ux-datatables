<?php

declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

enum ProductStatus: string implements TranslatableInterface
{
    case Active   = 'active';
    case Draft    = 'draft';
    case Archived = 'archived';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $translator->trans('enum.product_status.'.$this->value, [], null, $locale);
    }
}
