<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;
use Pentiminax\UX\DataTables\Enum\SelectItemType;
use Pentiminax\UX\DataTables\Enum\SelectStyle;

final class SelectExtension implements ExtensionInterface
{
    private const DEFAULT_SELECTOR = 'td, th';

    private bool $headerCheckbox = false;

    private bool $withCheckbox = false;

    public function __construct(
        private SelectStyle $style = SelectStyle::SINGLE,
        private bool $blurable = false,
        private string $className = 'selected',
        public bool $info = true,
        public SelectItemType $items = SelectItemType::ROW,
        public bool $keys = false,
        public string $selector = self::DEFAULT_SELECTOR,
        public bool $toggleable = true,
    ) {
    }

    public function getKey(): string
    {
        return 'select';
    }

    public function getStyle(): SelectStyle
    {
        return $this->style;
    }

    public function style(SelectStyle $style): self
    {
        $this->style = $style;

        return $this;
    }

    public function jsonSerialize(): array
    {
        $payload = [
            'blurable'       => $this->blurable,
            'className'      => $this->className,
            'info'           => $this->info,
            'items'          => $this->items->value,
            'keys'           => $this->keys,
            'style'          => $this->style->value,
            'toggleable'     => $this->toggleable,
            'headerCheckbox' => $this->headerCheckbox,
            'withCheckbox'   => $this->withCheckbox,
        ];

        if (self::DEFAULT_SELECTOR !== $this->selector) {
            $payload['selector'] = $this->selector;
        }

        return $payload;
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
