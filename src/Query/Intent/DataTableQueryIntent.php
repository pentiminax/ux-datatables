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
     * @param list<ColumnReadReference> $columns
     * @param list<ColumnSearchIntent>  $columnSearches
     * @param list<ColumnControlIntent> $columnControls
     */
    public function __construct(
        public ?int $draw,
        public PaginationIntent $pagination,
        public array $columns,
        public ?GlobalSearchIntent $globalSearch,
        public ?OrderIntent $order,
        public array $columnSearches,
        public array $columnControls,
    ) {
    }
}
