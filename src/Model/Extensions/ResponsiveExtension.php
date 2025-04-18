<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ResponsiveExtension implements ExtensionInterface
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