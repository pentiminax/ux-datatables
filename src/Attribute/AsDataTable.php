<?php

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    public function __construct(
        public readonly string $entityClass,
    ) {
    }
}
