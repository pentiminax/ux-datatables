<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    /**
     * @param string[]                                                                       $serializationGroups Groups used to filter exposed properties during column auto-detection
     * @param array{topics?: string|string[], withCredentials?: bool, debounceMs?: int}|bool $mercure             Mercure auto-wiring or explicit Mercure options
     * @param bool                                                                           $apiPlatform         Opt-in to API Platform integration (auto Ajax wiring, URL resolution, column auto-detection)
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly array $serializationGroups = [],
        public readonly bool|array $mercure = false,
        public readonly bool $apiPlatform = false,
        public readonly string $editModalTemplate = '',
        public readonly string $editModalAdapter = '',
    ) {
    }
}
