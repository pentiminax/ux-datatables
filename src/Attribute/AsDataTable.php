<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    /**
     * @param string[] $serializationGroups Groups used to filter exposed properties during column auto-detection
     * @param bool     $apiPlatform         Opt-in to API Platform integration (auto Ajax wiring, URL resolution, column auto-detection)
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly array $serializationGroups = [],
        public readonly bool $mercure = false,
        public readonly bool $apiPlatform = false,
        public readonly ?string $editModalTemplate = null,
        public readonly ?string $editModalAdapter = null,
    ) {
    }
}
