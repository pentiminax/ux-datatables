<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ResponsiveExtension extends AbstractExtension
{
    public function getKey(): string
    {
        return 'responsive';
    }

    public function jsonSerialize(): bool
    {
        return true;
    }
}
