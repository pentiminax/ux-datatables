<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * Filter that applies standard DataTables column-specific searches.
 *
 * Consumes the normalized {@see \Pentiminax\UX\DataTables\Query\Intent\ColumnSearchIntent}
 * criteria with AND logic, one condition per column search box. Delegates condition
 * building to the registry's 'contains' strategy, so overriding
 * AbstractDataTable::createSearchStrategyRegistry() customizes this search alongside
 * ColumnControl search instead of only the latter.
 *
 * Per-column resolution (handled by {@see \Pentiminax\UX\DataTables\Query\Strategy\ContainsSearchStrategy}):
 *
 *  1. Any search joins declared via {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::addSearchJoin()}
 *     are applied to the QueryBuilder first (idempotent).
 *  2. If the column implements {@see \Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface} and returns a
 *     non-null DQL fragment, that fragment is used verbatim.
 *  3. Otherwise the effective field path (from
 *     {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::setSearchField()} or
 *     {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::setField()}) drives
 *     the standard LIKE / exact-match predicate.
 *
 * Distinct from ColumnControlSearchFilter which handles custom column control searches.
 */
final class ColumnSearchFilter implements QueryFilterInterface
{
    public function __construct(
        private readonly SearchStrategyRegistry $registry = new DefaultSearchStrategyRegistry(),
    ) {
    }

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $strategy = $this->registry->get(ColumnControlLogic::Contains->value);

        foreach ($context->intent->columnSearches as $columnSearch) {
            $reference = $columnSearch->column;

            $column = $context->columnByName($reference->name);
            if (null === $column) {
                continue;
            }

            $search = new ColumnControlSearch($columnSearch->value, ColumnControlLogic::Contains, 'text');

            $strategy->apply($qb, $column, $search, $context->nextParamIndex(), $context->alias);
        }
    }
}
