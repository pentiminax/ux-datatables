<?php

namespace Pentiminax\UX\DataTables\Model\Extensions;

class FixedColumnsExtension extends AbstractExtension
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
