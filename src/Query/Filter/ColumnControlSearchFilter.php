<?php

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\QueryFilterInterface;
use Pentiminax\UX\DataTables\Query\Strategy\InListSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * Filter that applies column-specific search filters using strategy pattern.
 *
 * Delegates to registered search strategies based on the logic type
 * specified in each column's ColumnControlSearch.
 */
final class ColumnControlSearchFilter implements QueryFilterInterface
{
    public function __construct(
        private readonly SearchStrategyRegistry $registry,
    ) {
    }

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        /** @var AbstractColumn[] $searchableColumns */
        $searchableColumns = array_filter(
            $context->columns,
            static fn (AbstractColumn $column) => $column->isSearchable()
        );

        foreach ($searchableColumns as $index => $column) {
            $columnControl = $context->request->columns->getColumnByIndex($index)?->columnControl;
            $search        = $columnControl?->search;

            if ($columnControl && [] !== $columnControl->list) {
                $inStrategy = $this->registry->get('in');
                if ($inStrategy instanceof InListSearchStrategy) {
                    $inStrategy->applyForList($qb, $column->getField(), $columnControl->list, $context->alias);
                }
                continue;
            }

            if (!$search) {
                continue;
            }

            $strategy = $this->registry->get($search->logic);
            $strategy->apply($qb, $column, $search, $index, $context->alias);
        }
    }
}
