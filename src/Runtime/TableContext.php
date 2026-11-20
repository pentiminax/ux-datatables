<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * What the runtime hands a table while it calls one of its hooks, so the shared table instance holds none of it.
 *
 * The configure*() methods run with a context that carries no request at all.
 *
 * @internal
 */
final readonly class TableContext
{
    /**
     * @param (\Closure():list<AbstractColumn>)|null      $columns          the columns left after static permission filtering
     * @param (\Closure():RowMapperInterface)|null        $rowMapper        the row pipeline of the displayed table
     * @param (\Closure(mixed):array<string, mixed>)|null $defaultRowMapper maps a row from the table's own columns
     * @param (\Closure():iterable<AbstractColumn>)|null  $defaultColumns   the columns the bundle derives when configureColumns() declares none
     */
    public function __construct(
        public ?DataTableRequest $request = null,
        public ?Request $httpRequest = null,
        public ?\Closure $columns = null,
        public ?\Closure $rowMapper = null,
        public ?\Closure $defaultRowMapper = null,
        public ?\Closure $defaultColumns = null,
    ) {
    }
}
