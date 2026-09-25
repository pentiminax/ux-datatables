<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsDataTable
{
    /**
     * The class the table reads its shape from: the one carrying the #[DataTableColumn] and
     * #[DataTableFilter] declarations. Defaults to {@see $entityClass}.
     *
     * @var class-string
     */
    public readonly string $dataClass;

    /**
     * The class the runtime targets: Doctrine queries, mutations, row identifiers, Mercure topics
     * and API Platform metadata. Defaults to {@see $dataClass}.
     *
     * @var class-string
     */
    public readonly string $entityClass;

    /**
     * @param ?class-string                                                                  $dataClass           Class carrying the column and filter attributes, a DTO when the rows are projected
     * @param string[]                                                                       $serializationGroups Groups used to filter exposed properties during column auto-detection
     * @param array{topics?: string|string[], withCredentials?: bool, debounceMs?: int}|bool $mercure             Mercure auto-wiring or explicit Mercure options
     * @param bool                                                                           $apiPlatform         Opt-in to API Platform integration (auto Ajax wiring, URL resolution, column auto-detection)
     * @param ?string                                                                        $editModalTemplate   Twig template overriding the inline edit modal
     * @param ?string                                                                        $editModalAdapter    Modal adapter overriding the detected one
     * @param ?class-string                                                                  $entityClass         Doctrine entity the table queries and mutates, when it differs from the data class
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
        if (null === $dataClass && null === $entityClass) {
            throw new \InvalidArgumentException('#[AsDataTable] must name the class holding the data through its "dataClass" argument.');
        }

        // The attribute is instantiated once per class, so a typo fails here at rendering time
        // rather than later, inside Doctrine metadata or API Platform. The class must be
        // concrete: the features that read it as a mapped entity say so themselves.
        $this->dataClass   = self::assertClassExists($dataClass ?? $entityClass, 'data class');
        $this->entityClass = self::assertClassExists($entityClass ?? $dataClass, 'entity class');
    }

    /**
     * @return class-string
     */
    private static function assertClassExists(string $class, string $label): string
    {
        if (!class_exists($class)) {
            throw new \InvalidArgumentException(\sprintf('The %s "%s" declared on #[AsDataTable] must be an existing class.', $label, $class));
        }

        return $class;
    }
}
