<?php

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Column
{
    public function __construct(
        public readonly ?string $type = null,
        public readonly ?string $name = null,
        public readonly ?string $title = null,
        public readonly bool $orderable = true,
        public readonly bool $searchable = true,
        public readonly bool $visible = true,
        public readonly bool $exportable = true,
        public readonly bool $globalSearchable = true,
        public readonly ?string $width = null,
        public readonly ?string $className = null,
        public readonly ?string $cellType = null,
        public readonly ?string $render = null,
        public readonly ?string $defaultContent = null,
        public readonly ?string $field = null,
        public readonly ?string $format = null,
        public readonly int $priority = 0,
    ) {
    }
}
