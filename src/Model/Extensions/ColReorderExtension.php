<?php

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
