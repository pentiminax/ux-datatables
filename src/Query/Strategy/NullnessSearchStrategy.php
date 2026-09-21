<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for null/empty search logic.
 *
 * For numeric, date, and other non-text Doctrine columns, only checks NULL / NOT NULL.
 * For text columns, also includes empty-string checks.
 *
 * Native UUID/ULID columns must stay on the NULL-only path: PostgreSQL and SQL Server
 * reject `uuid = ''` the same way they reject `uuid LIKE`.
 *
 * The column's declared search joins are applied first, and its
 * {@see SearchableColumnInterface::getSearchField()} override replaces getField() when set.
 * {@see SearchableColumnInterface::buildSearchPredicate()} is deliberately not consulted: a nullness
 * check is a property of the field itself, which an open-ended condition string built for a
 * search term cannot stand in for.
 *
 * A field the root entity does not map is skipped here rather than in the filter, so a column
 * that builds its own predicate stays searchable on the Contains logic.
 */
final class NullnessSearchStrategy implements SearchStrategyInterface
{
    private const array NULL_ONLY_TYPES = ['date', 'datetime', 'datetime-local', 'time', 'num', 'number', 'numeric'];

    public function __construct(
        private readonly bool $negated = false,
    ) {
    }

    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        RelationFieldResolver::applySearchJoins($qb, $column);

        $fieldPath = RelationFieldResolver::resolveSearchField($column);
        if (null === $fieldPath) {
            return;
        }

        if (!RelationFieldResolver::supportsSearchFiltering($qb, $fieldPath)) {
            return;
        }

        $field      = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $expr       = $qb->expr();
        $isNullOnly = $column->isNumber()
            || $column->isDate()
            || \in_array(strtolower($search->type), self::NULL_ONLY_TYPES, true)
            || !RelationFieldResolver::supportsTextSearch($qb, $fieldPath);

        if ($isNullOnly) {
            $qb->andWhere($this->negated ? $expr->isNotNull($field) : $expr->isNull($field));

            return;
        }

        $qb->andWhere($this->negated
            ? $expr->andX(
                $expr->isNotNull($field),
                $expr->neq($field, $expr->literal(''))
            )
            : $expr->orX(
                $expr->isNull($field),
                $expr->eq($field, $expr->literal(''))
            ));
    }

    public function getLogic(): string
    {
        return $this->negated ? 'notEmpty' : 'empty';
    }
}
