<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Filters;

/**
 * What a table class configures, built once by {@see TableDefinitionBuilder} and only read afterwards.
 *
 * It is a pure function of the table class: it never holds the request, the security token, the
 * session, the locale, or any per-user decision. Permissions are evaluated per request by the runtime.
 * Treat the DataTable it carries as read-only.
 */
final readonly class TableDefinition
{
    /**
     * @param class-string<AbstractDataTable> $class
     * @param list<AbstractColumn>            $columns the configured columns, action columns included
     */
    public function __construct(
        public string $class,
        public DataTable $table,
        public array $columns,
        public Filters $filters,
        public ?AsDataTable $asDataTable,
        public ResolvedOptIns $optIns,
    ) {
    }

    /**
     * @return class-string|null
     */
    public function entityClass(): ?string
    {
        return $this->asDataTable?->entityClass;
    }
}
