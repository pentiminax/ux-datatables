<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;
use Pentiminax\UX\DataTables\Query\ColumnSearchResolver;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\SearchPredicateFactory;

/**
 * Filter that applies standard DataTables column-specific searches.
 *
 * Consumes the normalized {@see \Pentiminax\UX\DataTables\Query\Intent\ColumnSearchIntent}
 * criteria with AND logic. Delegates condition building to SearchPredicateFactory.
 *
 * Per-column resolution order:
 *
 *  1. Any search joins declared via {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::addSearchJoin()}
 *     are applied to the QueryBuilder first (idempotent).
 *  2. If the column implements {@see SearchableColumnInterface} and returns a
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
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        foreach ($context->intent->columnSearches as $columnSearch) {
            $reference = $columnSearch->column;

            $column = $context->columnByName($reference->name);
            if (null === $column) {
                continue;
            }

            // Apply any column-declared search joins before resolving the predicate.
            ColumnSearchResolver::applySearchJoins($qb, $column);

            $paramName = \sprintf('column_search_param_%d', $context->paramIndexFor($reference));

            // Custom predicate (SearchableColumnInterface / setSearchPredicate()).
            if ($column instanceof SearchableColumnInterface) {
                $custom = $column->buildSearchPredicate($qb, $context->alias, $columnSearch->value, $paramName);
                if (null !== $custom) {
                    $qb->andWhere($custom);
                    continue;
                }
            }

            // Standard field-based predicate.
            $field = ColumnSearchResolver::resolveField($column);
            if (null === $field) {
                continue;
            }

            $condition = SearchPredicateFactory::build($qb, $column, $context->alias, $field, $columnSearch->value, $paramName);

            if (null !== $condition) {
                $qb->andWhere($condition);
            }
        }
    }
}
