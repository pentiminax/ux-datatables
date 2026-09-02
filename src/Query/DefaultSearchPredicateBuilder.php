<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;

/**
 * Default {@see SearchPredicateBuilderInterface}, dispatching on the column type.
 *
 * For numeric columns: exact match when the value is numeric, null otherwise.
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
        if ($column->isNumber() || $forceNumeric) {
            if (!is_numeric($value)) {
                return null;
            }

            return SearchConditionBuilder::numeric($qb, $alias, $field, $value, $paramName);
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
}
