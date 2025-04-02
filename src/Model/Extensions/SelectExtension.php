<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SelectStyle;

readonly class SelectExtension implements ExtensionInterface
{
    public function __construct(
        private SelectStyle $style = SelectStyle::SINGLE
    ) {
    }

    public function getKey(): string
    {
        return 'select';
    }

    public function toArray(): array
    {
        return [
            'style' => $this->style->value
        ];
    }
}