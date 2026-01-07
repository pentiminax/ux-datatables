<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ScrollerExtension extends AbstractExtension
{
    public function getKey(): string
    {
        return 'scroller';
    }

    public function jsonSerialize(): bool
    {
        return true;
    }
}
