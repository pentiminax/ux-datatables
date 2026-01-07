<?php

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
