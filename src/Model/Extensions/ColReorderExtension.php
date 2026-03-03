<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ColReorderExtension extends AbstractExtension
{
    public function getKey(): string
    {
        return 'colReorder';
    }

    public function jsonSerialize(): bool
    {
        return true;
    }
}
