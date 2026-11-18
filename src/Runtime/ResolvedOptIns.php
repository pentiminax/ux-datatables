<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * The opt-ins a table declares on `#[AsDataTable]` and through its fluent API, and the data source
 * mode they lead to, resolved once after `configureDataTable()` so every consumer reads the same answer.
 *
 * A fluent value overrides the attribute's, field by field; null or a blank string means unset, so
 * `apiPlatform` has three states. `mercure` is the fluent config when the table set one, else the
 * attribute's value. `serverSide` says whether the server pages the rows: the bundle's endpoint, or
 * a custom `ajax()` endpoint the table declared `serverSide()` for.
 *
 * @internal
 */
final readonly class ResolvedOptIns
{
    /**
     * @param class-string|null                       $entityClass
     * @param MercureConfig|array<string, mixed>|bool $mercure
     * @param string[]                                $serializationGroups
     */
    public function __construct(
        public ?string $entityClass = null,
        public ?bool $apiPlatform = null,
        public MercureConfig|array|bool $mercure = false,
        public ?string $editModalTemplate = null,
        public ?string $editModalAdapter = null,
        public array $serializationGroups = [],
        public DataSourceMode $dataSourceMode = DataSourceMode::ClientHydrated,
        public bool $serverSide = false,
    ) {
    }

    /**
     * @throws \LogicException when the table combines data() with an API Platform opt-in
     */
    public static function resolve(DataTable $table, ?AsDataTable $asDataTable, ?ApiResourceCollectionUrlResolver $collectionUrlResolver = null): self
    {
        $entityClass = $asDataTable?->entityClass;
        $apiPlatform = $table->getBundleOption('apiPlatform') ?? (true === $asDataTable?->apiPlatform ? true : null);

        $dataSourceMode = DataSourceMode::resolve($table, $entityClass, true === $apiPlatform, $collectionUrlResolver);

        return new self(
            entityClass: $entityClass,
            apiPlatform: $apiPlatform,
            mercure: $table->getMercureConfig() ?? $asDataTable?->mercure ?? false,
            editModalTemplate: self::firstText($table->getEditModalTemplate(), $asDataTable?->editModalTemplate),
            editModalAdapter: self::firstText($table->getEditModalAdapter(), $asDataTable?->editModalAdapter),
            serializationGroups: $asDataTable?->serializationGroups ?? [],
            dataSourceMode: $dataSourceMode,
            serverSide: DataSourceMode::ServerSide === $dataSourceMode || true === $table->getOption('serverSide'),
        );
    }

    public function optsIntoApiPlatform(): bool
    {
        return true === $this->apiPlatform;
    }

    private static function firstText(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (null !== $value && '' !== trim($value)) {
                return $value;
            }
        }

        return null;
    }
}
