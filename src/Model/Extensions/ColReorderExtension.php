<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Extensions;

class ColReorderExtension extends AbstractExtension
{
    public function __construct(
        private readonly bool $enable = true,
        private readonly string $columns = '',
    ) {
    }

    public function getKey(): string
    {
        return 'colReorder';
    }

    public function jsonSerialize(): array
    {
        return [
            'enable'  => $this->enable,
            'columns' => $this->columns,
        ];
    }
}
