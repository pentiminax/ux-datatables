<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

/**
 * Provider-neutral, normalized read intent built once from a DataTableRequest plus
 * configured columns.
 *
 * Contains no Doctrine classes, DQL strings, aliases, QueryBuilder, or raw DataTables
 * request indexes. Providers consume this instead of re-resolving the raw request.
 */
final readonly class DataTableQueryIntent
{
    /**
     * @param list<ColumnReadReference>                               $columns
     * @param 'asc'|'desc'|null                                       $orderDir
     * @param list<array{column: ColumnReadReference, value: string}> $columnSearches
     * @param list<ColumnControlIntent>                               $columnControls
     */
    public function __construct(
        public ?int $draw,
        public int $offset,
        public ?int $limit,
        public array $columns,
        public ?string $globalSearch,
        public ?ColumnReadReference $orderColumn,
        public ?string $orderDir,
        public array $columnSearches,
        public array $columnControls,
    ) {
    }
}
