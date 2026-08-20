<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ScrollerExtension extends AbstractExtension
{
    public function __construct(
        private readonly float $boundaryScale = 0.5,
        private readonly int $displayBuffer = 9,
        private readonly int|string $rowHeight = 'auto',
        private readonly int $serverWait = 200,
    ) {
    }

    public function getKey(): string
    {
        return 'scroller';
    }

    public function jsonSerialize(): array
    {
        return [
            'boundaryScale' => $this->boundaryScale,
            'displayBuffer' => $this->displayBuffer,
            'rowHeight'     => $this->rowHeight,
            'serverWait'    => $this->serverWait,
        ];
    }
}
