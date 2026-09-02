<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;

/**
 * Default {@see SearchPredicateBuilderInterface}, dispatching on the column type.
 *
 * A column's own {@see ColumnInterface::buildSearchPredicate()} wins over every branch below:
 * a column that builds its condition itself has said the type-based predicates cannot express
 * what it needs. It is consulted first and its result returned verbatim; returning null there
 * means "no opinion", not "skip", and falls through to the type dispatch.
 *
 * For numeric columns: exact match when the value can be bound to the field's Doctrine
 * type, null otherwise. is_numeric() is not enough on its own: "1.5" and "1e2" are
 * numeric, but PostgreSQL rejects them on integer columns (`invalid input syntax for
 * type integer`) and MySQL coerces the decimal, matching the wrong rows. When the mapped
 * type is known, {@see NumericSearchTerm} applies the same skip/normalize contract as
 * {@see Strategy\ComparisonSearchStrategy}.
 * For native UUID/ULID columns: exact match when the value is a well-formed identifier of
 * that field's type, null otherwise.
 * For other columns: LIKE %value% when the field supports search filtering, null otherwise.
 *
 * A column is treated as numeric when {@see ColumnInterface::isNumber()} is true or when
 * the caller forces numeric handling via $forceNumeric (e.g. based on an external type hint).
 */
final class DefaultSearchPredicateBuilder implements SearchPredicateBuilderInterface
{
    public function build(
        QueryBuilder $qb,
        ColumnInterface $column,
        string $alias,
        string $field,
        string $value,
        string $paramName,
        bool $forceNumeric = false,
    ): ?string {
        $custom = $column->buildSearchPredicate($qb, $alias, $value, $paramName);

        if (null !== $custom) {
            return $custom;
        }

        if ($column->isNumber() || $forceNumeric) {
            return $this->buildNumeric($qb, $alias, $field, $value, $paramName);
        }

        if ($column->isDate()) {
            return null;
        }

        $uuidType = RelationFieldResolver::resolveUuidFieldType($qb, $field);

        if (null !== $uuidType) {
            $identifier = UuidSearchTerm::normalize($value, $uuidType);

            if (null === $identifier) {
                return null;
            }

            return SearchConditionBuilder::equality($qb, $alias, $field, $identifier, $paramName, $uuidType);
        }

        if (!RelationFieldResolver::supportsTextSearch($qb, $field)) {
            return null;
        }

        return SearchConditionBuilder::text($qb, $alias, $field, $value, $paramName);
    }

    /**
     * Exact-match a numeric search term, or return null when it cannot be bound to the
     * field's mapped type. The unknown-type fallback keeps the historical is_numeric()
     * gate used when the query builder has no root-entity metadata (unit tests, non-Doctrine
     * builders): without a type we cannot tell integer from float, so a decimal term is
     * still bound rather than skipped.
     */
    private function buildNumeric(
        QueryBuilder $qb,
        string $alias,
        string $field,
        string $value,
        string $paramName,
    ): ?string {
        $numericType = RelationFieldResolver::resolveIntegerFieldType($qb, $field)
            ?? RelationFieldResolver::resolveFloatFieldType($qb, $field);

        if (null !== $numericType) {
            $normalized = NumericSearchTerm::normalize($value, $numericType);

            if (null === $normalized) {
                return null;
            }

            return SearchConditionBuilder::equality($qb, $alias, $field, $normalized, $paramName, $numericType);
        }

        if (!is_numeric($value)) {
            return null;
        }

        return SearchConditionBuilder::numeric($qb, $alias, $field, $value, $paramName);
    }
}
