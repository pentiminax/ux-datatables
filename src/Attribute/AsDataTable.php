<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    /**
     * @param class-string                                                                   $entityClass         Doctrine entity class linked to the table
     * @param string[]                                                                       $serializationGroups Groups used to filter exposed properties during column auto-detection
     * @param array{topics?: string|string[], withCredentials?: bool, debounceMs?: int}|bool $mercure             Mercure auto-wiring or explicit Mercure options
     * @param bool                                                                           $apiPlatform         Opt-in to API Platform integration (auto Ajax wiring, URL resolution, column auto-detection)
     * @param ?string                                                                        $editModalTemplate   Twig template overriding the inline edit modal
     * @param ?string                                                                        $editModalAdapter    Modal adapter overriding the detected one
     */
    public function __construct(
        public readonly string $entityClass,
        public readonly array $serializationGroups = [],
        public readonly bool|array $mercure = false,
        public readonly bool $apiPlatform = false,
        public readonly ?string $editModalTemplate = null,
        public readonly ?string $editModalAdapter = null,
    ) {
        // The attribute is instantiated once per class, so a typo fails here at rendering time
        // rather than later, inside Doctrine metadata or API Platform.
        if (!class_exists($entityClass) && !interface_exists($entityClass)) {
            throw new \InvalidArgumentException(\sprintf('The entity class "%s" declared on #[AsDataTable] does not exist.', $entityClass));
        }
    }
}
