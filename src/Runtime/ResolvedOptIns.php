<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Mercure\MercureConfig;
use Pentiminax\UX\DataTables\Model\DataTable;

/**
 * The opt-ins a table declares on `#[AsDataTable]` and through its fluent API, merged once after
 * `configureDataTable()` so every consumer reads the same answer.
 *
 * A fluent edit modal value overrides the attribute's when it is not blank. `apiPlatform` keeps the
 * 1.x rule that either side opting in counts: 2.0 lets a fluent `false` win, which
 * `$apiPlatformOptOutOverridden` flags. `mercure` is the fluent config when the table set one, else
 * the attribute's value.
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
        public bool $apiPlatform = false,
        public bool $apiPlatformOptOutOverridden = false,
        public MercureConfig|array|bool $mercure = false,
        public ?string $editModalTemplate = null,
        public ?string $editModalAdapter = null,
        public array $serializationGroups = [],
    ) {
    }

    public static function resolve(DataTable $table, ?AsDataTable $asDataTable): self
    {
        $fluentApiPlatform = $table->getOption('apiPlatform');
        $attributeOptIn    = true === $asDataTable?->apiPlatform;

        return new self(
            entityClass: $asDataTable?->entityClass,
            apiPlatform: $attributeOptIn || true                  === $fluentApiPlatform,
            apiPlatformOptOutOverridden: $attributeOptIn && false === $fluentApiPlatform,
            mercure: $table->getMercureConfig() ?? $asDataTable?->mercure ?? false,
            editModalTemplate: self::firstText($table->getEditModalTemplate(), $asDataTable?->editModalTemplate),
            editModalAdapter: self::firstText($table->getEditModalAdapter(), $asDataTable?->editModalAdapter),
            serializationGroups: $asDataTable?->serializationGroups ?? [],
        );
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
