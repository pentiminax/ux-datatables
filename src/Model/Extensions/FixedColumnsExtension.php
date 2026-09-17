<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

use Pentiminax\UX\DataTables\Contracts\ExtensionInterface;

class FixedColumnsExtension implements ExtensionInterface
{
    public function __construct(
        private readonly int $start = 1,
        private readonly int $end = 0,
    ) {
    }

    public function getKey(): string
    {
        return 'fixedColumns';
    }

    public function jsonSerialize(): array
    {
        return [
            'start' => $this->start,
            'end'   => $this->end,
        ];
    }
}
