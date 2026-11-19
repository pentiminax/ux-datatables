<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column;

/**
 * The columns the current user may see on one request, and the row paths of those they may not.
 *
 * Built by {@see ColumnResolver::visibleColumns()} at a request entry point and handed down to
 * every consumer, so none of them decides column visibility on its own. Per request and per user:
 * never store it on a container-shared service.
 *
 * @internal
 */
final class VisibleColumns
{
    /**
     * @param list<AbstractColumn> $columns     the granted columns, action columns cloned with their actions filtered
     * @param list<string>         $deniedPaths row keys and read paths of the denied columns
     */
    public function __construct(
        public readonly array $columns,
        public readonly array $deniedPaths,
        public readonly ?string $dataTableClass,
    ) {
    }
}
