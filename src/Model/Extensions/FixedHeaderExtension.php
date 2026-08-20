<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

final class FixedHeaderExtension extends AbstractExtension
{
    public function __construct(
        private readonly bool $header = true,
        private readonly bool $footer = false,
        private readonly int $headerOffset = 0,
        private readonly int $footerOffset = 0,
    ) {
    }

    public function getKey(): string
    {
        return 'fixedHeader';
    }

    public function jsonSerialize(): array
    {
        return [
            'header'       => $this->header,
            'footer'       => $this->footer,
            'headerOffset' => $this->headerOffset,
            'footerOffset' => $this->footerOffset,
        ];
    }
}
