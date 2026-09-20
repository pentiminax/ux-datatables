<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    /**
     * The class the table reads its shape from.
     *
     * @var class-string
     */
    public readonly string $dataClass;

    /**
     * Same value as {@see $dataClass}, kept for the whole 1.x line.
     *
     * @var class-string
     *
     * @deprecated since 1.1, read {@see $dataClass} instead. Removed in 2.0.
     */
    public readonly string $entityClass;

    /**
     * @param ?class-string                                                                  $dataClass           Class holding the data: a Doctrine entity, or any class when the table is fed from elsewhere
     * @param string[]                                                                       $serializationGroups Groups used to filter exposed properties during column auto-detection
     * @param array{topics?: string|string[], withCredentials?: bool, debounceMs?: int}|bool $mercure             Mercure auto-wiring or explicit Mercure options
     * @param bool                                                                           $apiPlatform         Opt-in to API Platform integration (auto Ajax wiring, URL resolution, column auto-detection)
     * @param ?string                                                                        $editModalTemplate   Twig template overriding the inline edit modal
     * @param ?string                                                                        $editModalAdapter    Modal adapter overriding the detected one
     * @param ?class-string                                                                  $entityClass         Deprecated since 1.1, pass `dataClass` instead
     */
    public function __construct(
        ?string $dataClass = null,
        public readonly array $serializationGroups = [],
        public readonly bool|array $mercure = false,
        public readonly bool $apiPlatform = false,
        public readonly ?string $editModalTemplate = null,
        public readonly ?string $editModalAdapter = null,
        ?string $entityClass = null,
    ) {
        if (null !== $dataClass && null !== $entityClass) {
            throw new \InvalidArgumentException('#[AsDataTable] accepts either "dataClass" or the deprecated "entityClass", not both.');
        }

        if (null !== $entityClass) {
            trigger_deprecation('pentiminax/ux-datatables', '1.1', 'The "entityClass" argument of #[AsDataTable] is deprecated, use "dataClass" instead.');
        }

        $resolved = $dataClass ?? $entityClass;

        if (null === $resolved) {
            throw new \InvalidArgumentException('#[AsDataTable] must name the class holding the data through its "dataClass" argument.');
        }

        // The attribute is instantiated once per class, so a typo fails here at rendering time
        // rather than later, inside Doctrine metadata or API Platform. The class must be
        // concrete: the features that read it as a mapped entity say so themselves.
        if (!class_exists($resolved)) {
            throw new \InvalidArgumentException(\sprintf('The data class "%s" declared on #[AsDataTable] must be an existing class.', $resolved));
        }

        $this->dataClass   = $resolved;
        $this->entityClass = $resolved;
    }
}
