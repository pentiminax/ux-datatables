<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class KeyTableExtension extends AbstractExtension
{
    public function getKey(): string
    {
        return 'keys';
    }

    public function jsonSerialize(): bool
    {
        return true;
    }
}
