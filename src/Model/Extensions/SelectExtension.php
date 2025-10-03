<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Enum\SelectItemType;
use Pentiminax\UX\DataTables\Enum\SelectStyle;

final class SelectExtension extends AbstractExtension
{
    private bool $headerCheckbox = false;

    private bool $withCheckbox = false;

    public function __construct(
        private SelectStyle $style = SelectStyle::SINGLE,
        private bool $blurable = false,
        private string $className = 'selected',
        public bool $info = true,
        public SelectItemType $items = SelectItemType::ROW,
        public bool $keys = false,
        public string $selector = 'td, th',
        public bool $toggleable = true,
    ) {
    }

    public function getKey(): string
    {
        return 'select';
    }

    public function jsonSerialize(): array
    {
        return [
            'blurable' => $this->blurable,
            'className' => $this->className,
            'info' => $this->info,
            'items' => $this->items->value,
            'keys' => $this->keys,
            'style' => $this->style->value,
            'toggleable' => $this->toggleable,
            'headerCheckbox' => $this->headerCheckbox,
            'withCheckbox' => $this->withCheckbox
        ];
    }

    public function headerCheckbox(bool $headerCheckbox = true): self
    {
        $this->headerCheckbox = $headerCheckbox;

        return $this;
    }


    public function withCheckbox(bool $withCheckbox = true): self
    {
        $this->withCheckbox = $withCheckbox;

        return $this;
    }
}