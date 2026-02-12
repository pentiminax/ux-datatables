<?php

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    /**
     * @param string[] $serializationGroups Groups used to filter exposed properties during column auto-detection
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly array $serializationGroups = [],
    ) {
    }
}
