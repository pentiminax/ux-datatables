<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Builder;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntentFactoryInterface;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * Assembles and runs the server-side query pipeline for a DataTable request.
 *
 * Extracted from AbstractDataTable so the mechanical wiring — building the
 * normalized query intent, indexing columns by name, running the default
 * {@see QueryFilterChain} and applying user-declared {@see Filters} — lives in
 * one collaborator that can be unit-tested in isolation. AbstractDataTable keeps
 * only its user-facing override hooks (customizeQueryBuilder, the search-strategy
 * registry) and delegates the assembly here.
 */
final class QueryFilterPipeline
{
    private const ROOT_ALIAS = 'e';

    public function __construct(
        private readonly DataTableQueryIntentFactoryInterface $intentFactory,
    ) {
    }

    /**
     * @param list<ColumnInterface> $columns Configured, permission-filtered columns in display order
     */
    public function apply(
        QueryBuilder $qb,
        DataTableRequest $request,
        array $columns,
        ?Filters $filters,
        SearchStrategyRegistry $registry,
    ): QueryBuilder {
        $intent = $this->intentFactory->create($request, array_values($columns));

        $columnsByName = [];
        foreach ($columns as $column) {
            $columnsByName[$column->getName()] = $column;
        }

        $context = new QueryFilterContext(
            intent: $intent,
            columns: $columnsByName,
            alias: self::ROOT_ALIAS,
        );

        $qb = QueryFilterChain::createDefault($registry)->apply($qb, $context);

        $this->applyConfiguredFilters($qb, $request, $filters);

        return $qb;
    }

    private function applyConfiguredFilters(QueryBuilder $qb, DataTableRequest $request, ?Filters $filters): void
    {
        if (null === $filters || $filters->isEmpty()) {
            return;
        }

        foreach ($filters->getFilters() as $filter) {
            $value = $request->filters[$filter->getName()] ?? null;

            if (null === $value || '' === $value || [] === $value) {
                continue;
            }

            $filter->apply($qb, $value, self::ROOT_ALIAS);
        }
    }
}
