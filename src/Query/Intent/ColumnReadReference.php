<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

use Pentiminax\UX\DataTables\Enum\ColumnType;

/**
 * Provider-neutral facts about a single configured column.
 *
 * Carries only normalized configuration. Doctrine concerns (raw order expressions,
 * aliases, joins, parameter names) are intentionally absent and resolved by name
 * where a provider builds its query.
 */
final readonly class ColumnReadReference
{
    public function __construct(
        public string $name,
        public ?string $fieldPath,
        public ColumnType $type,
        public bool $searchable,
        public bool $globalSearchable,
        public bool $orderable,
    ) {
    }
}
