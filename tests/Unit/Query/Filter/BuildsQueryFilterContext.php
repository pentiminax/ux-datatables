<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;

/**
 * Builds a QueryFilterContext from a request and configured columns through the real
 * intent factory, so filter tests exercise the same normalized intent the chain uses.
 *
 * @internal
 */
trait BuildsQueryFilterContext
{
    /**
     * @param list<ColumnInterface> $columns
     */
    private function context(DataTableRequest $request, array $columns): QueryFilterContext
    {
        $intent = (new DefaultDataTableQueryIntentFactory())->create($request, $columns);

        $columnsByName = [];
        foreach ($columns as $column) {
            $columnsByName[$column->getName()] = $column;
        }

        return new QueryFilterContext($intent, $columnsByName, 'e');
    }
}
