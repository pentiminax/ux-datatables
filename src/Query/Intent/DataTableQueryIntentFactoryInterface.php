<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Intent;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

interface DataTableQueryIntentFactoryInterface
{
    /**
     * @param list<ColumnInterface> $columns configured, permission-filtered columns in display order
     *
     * @throws InvalidQueryIntentException for impossible programmer/configuration states only
     */
    public function create(DataTableRequest $request, array $columns): DataTableQueryIntent;
}
