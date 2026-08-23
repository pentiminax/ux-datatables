<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchAwareColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\ColumnSearchResolver;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Strategy for null/empty search logic.
 *
 * For numeric, date, and other non-text Doctrine columns, only checks NULL / NOT NULL.
 * For text columns, also includes empty-string checks.
 *
 * Respects {@see SearchAwareColumnInterface::getSearchField()} for field resolution and
 * applies any column-declared search joins before building the predicate.
 * The {@see \Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface}
 * custom predicate is intentionally not invoked: nullness checks are inherently
 * field-level operations that a generic open-ended closure cannot safely compose.
 *
 * Native UUID/ULID columns must stay on the NULL-only path: PostgreSQL and SQL Server
 * reject `uuid = ''` the same way they reject `uuid LIKE`.
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
        ColumnSearchResolver::applySearchJoins($qb, $column);

        $effectiveField = ColumnSearchResolver::resolveField($column);
        if (null === $effectiveField) {
            return;
        }

        $field      = RelationFieldResolver::resolve($qb, $alias, $effectiveField);
        $expr       = $qb->expr();
        $isNullOnly = $column->isNumber()
            || $column->isDate()
            || \in_array(strtolower($search->type), self::NULL_ONLY_TYPES, true)
            || !RelationFieldResolver::supportsTextSearch($qb, $effectiveField);

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
