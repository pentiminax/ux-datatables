<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Filter that applies global search across all globally searchable columns.
 *
 * Reads the trimmed non-empty {@see \Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent::$globalSearch}
 * string and the globally searchable column references from the intent. Delegates
 * condition building to the injected SearchPredicateBuilderInterface, so overriding
 * AbstractDataTable::createSearchPredicateBuilder() customizes this search. All conditions
 * are combined with OR logic: each column's condition must stay a returned DQL fragment
 * rather than a QueryBuilder::andWhere() call, since only this filter knows the columns
 * need to be OR'd together rather than required individually.
 *
 * Per column, the joins declared with {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::addSearchJoin()}
 * are applied first, then the search field override from
 * {@see \Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface::getSearchField()} replaces the
 * intent's display field path.
 */
final class GlobalSearchFilter implements QueryFilterInterface
{
    public function __construct(
        private readonly SearchPredicateBuilderInterface $predicateBuilder = new DefaultSearchPredicateBuilder(),
    ) {
    }

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        $globalSearch = $context->intent->globalSearch;
        if (null === $globalSearch) {
            return;
        }

        $conditions = [];

        foreach ($context->intent->columns as $reference) {
            if (!$reference->globalSearchable) {
                continue;
            }

            $column = $context->columnByName($reference->name);

            if (null === $column) {
                continue;
            }

            RelationFieldResolver::applySearchJoins($qb, $column);

            $field = RelationFieldResolver::resolveSearchField($column) ?? $reference->fieldPath;

            if (null === $field) {
                continue;
            }

            $paramName = \sprintf('search_param_%d', $context->nextParamIndex());
            $condition = $this->predicateBuilder->build($qb, $column, $context->alias, $field, $globalSearch, $paramName);

            if (null !== $condition) {
                $conditions[] = $condition;
            }
        }

        if ([] !== $conditions) {
            $qb->andWhere($qb->expr()->orX(...$conditions));
        }
    }
}
