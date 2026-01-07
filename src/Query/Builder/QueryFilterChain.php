<?php

namespace Pentiminax\UX\DataTables\Query\Builder;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\Filter\ColumnControlSearchFilter;
use Pentiminax\UX\DataTables\Query\Filter\GlobalSearchFilter;
use Pentiminax\UX\DataTables\Query\Filter\OrderFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * Chain of Responsibility for applying multiple filters in sequence.
 *
 * Filters are applied in the order they are added to the chain.
 * Provides a fluent interface for building filter chains and a static
 * factory method for creating the default chain with standard filters.
 */
final class QueryFilterChain
{
    /**
     * @var QueryFilterInterface[]
     */
    private array $filters = [];

    public function addFilter(QueryFilterInterface $filter): self
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * Apply all filters in sequence to the QueryBuilder.
     *
     * @param QueryBuilder       $qb      The query builder to modify
     * @param QueryFilterContext $context The filter context with request data
     *
     * @return QueryBuilder The modified query builder
     */
    public function apply(QueryBuilder $qb, QueryFilterContext $context): QueryBuilder
    {
        foreach ($this->filters as $filter) {
            $filter->apply($qb, $context);
        }

        return $qb;
    }

    /**
     * Create a default filter chain with standard filters.
     *
     * The default chain includes:
     * 1. OrderFilter - Applies sorting
     * 2. GlobalSearchFilter - Applies global search across all searchable columns
     * 3. ColumnControlSearchFilter - Applies column-specific filters using strategies
     */
    public static function createDefault(SearchStrategyRegistry $registry): self
    {
        return (new self())
            ->addFilter(new OrderFilter())
            ->addFilter(new GlobalSearchFilter())
            ->addFilter(new ColumnControlSearchFilter($registry));
    }
}
