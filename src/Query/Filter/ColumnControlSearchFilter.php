<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\BooleanSearchTerm;
use Pentiminax\UX\DataTables\Query\DateSearchTerm;
use Pentiminax\UX\DataTables\Query\Intent\ColumnControlIntent;
use Pentiminax\UX\DataTables\Query\NumericSearchTerm;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\UuidSearchTerm;

/**
 * Filter that applies column-control search criteria using the strategy pattern.
 *
 * Consumes the normalized {@see ColumnControlIntent} criteria. List criteria are
 * applied through an explicit IN branch on text columns; native UUID/ULID, numeric,
 * date, and boolean columns expand to typed equalities so invalid terms never reach
 * setParameter() and driver-specific types still match. Scalar criteria delegate to
 * the registered search strategy for their logic.
 *
 * A list value of '' (the empty string) is matched with IS NULL rather than being
 * bound into the IN clause: an optional relation's dot-path field (e.g. a
 * ColumnControl searchList option representing "no value assigned") resolves to
 * NULL through its LEFT JOIN, never to an empty string, so IN ('') would silently
 * match nothing. This mirrors ColumnControl's own client-side convention, where an
 * option with an empty label/value already renders as a distinct "Empty" entry.
 *
 * Per column, the joins declared with {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::addSearchJoin()}
 * are applied before anything else, and the search field override from
 * {@see ColumnInterface::getSearchField()} replaces the
 * intent's display field path -- including for the searchability guard below, so a column
 * whose display field is virtual is still searched through its override.
 *
 * {@see ColumnInterface::buildSearchPredicate()} is not
 * consulted here: list, comparison, and nullness criteria each encode a predicate shape of
 * their own that a single open-ended condition string cannot express. Only the Contains logic
 * delegates to it, through {@see \Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface}.
 */
final class ColumnControlSearchFilter implements QueryFilterInterface
{
    public function __construct(
        private readonly SearchStrategyRegistry $registry,
    ) {
    }

    public function apply(QueryBuilder $qb, QueryFilterContext $context): void
    {
        foreach ($context->intent->columnControls as $control) {
            $column = $context->columnByName($control->column->name);
            if (null === $column) {
                continue;
            }

            RelationFieldResolver::applySearchJoins($qb, $column);

            $field = $column->getSearchField() ?? $control->column->fieldPath;
            if (null === $field) {
                continue;
            }

            if (!RelationFieldResolver::supportsSearchFiltering($qb, $field)) {
                continue;
            }

            if ($control->isList()) {
                $this->applyList($qb, $field, $control->values, $context->alias);

                continue;
            }

            $this->applyScalar($qb, $context, $control, $column);
        }
    }

    /**
     * @param list<mixed> $values
     */
    private function applyList(QueryBuilder $qb, string $field, array $values, string $alias): void
    {
        if ([] === $values) {
            return;
        }

        // The UUID, date, integer, float, and boolean type lists are pairwise disjoint and
        // resolveFieldType() returns a single Doctrine type, so at most one branch can match.
        $uuidType = RelationFieldResolver::resolveUuidFieldType($qb, $field);
        if (null !== $uuidType) {
            $this->applyUuidList($qb, $field, $values, $alias, $uuidType);

            return;
        }

        $dateType = RelationFieldResolver::resolveDateFieldType($qb, $field);
        if (null !== $dateType) {
            $this->applyTypedEqualityList(
                $qb,
                $field,
                $values,
                $alias,
                $dateType,
                DateSearchTerm::normalize(...),
            );

            return;
        }

        $integerType = RelationFieldResolver::resolveIntegerFieldType($qb, $field);
        if (null !== $integerType) {
            $this->applyTypedEqualityList(
                $qb,
                $field,
                $values,
                $alias,
                $integerType,
                static fn (string $value): ?string => NumericSearchTerm::normalize($value, $integerType),
            );

            return;
        }

        $floatType = RelationFieldResolver::resolveFloatFieldType($qb, $field);
        if (null !== $floatType) {
            $this->applyTypedEqualityList(
                $qb,
                $field,
                $values,
                $alias,
                $floatType,
                static fn (string $value): ?string => NumericSearchTerm::normalize($value, $floatType),
            );

            return;
        }

        $booleanType = RelationFieldResolver::resolveBooleanFieldType($qb, $field);
        if (null !== $booleanType) {
            $this->applyTypedEqualityList(
                $qb,
                $field,
                $values,
                $alias,
                $booleanType,
                BooleanSearchTerm::normalize(...),
            );

            return;
        }

        $expr           = RelationFieldResolver::resolve($qb, $alias, $field);
        $nonEmptyValues = array_values(array_filter($values, static fn (mixed $value): bool => '' !== $value));

        if (\count($nonEmptyValues) === \count($values)) {
            $paramName = \sprintf(':%s_in', str_replace('.', '_', $field));

            $qb->andWhere(\sprintf('%s IN (%s)', $expr, $paramName));
            $qb->setParameter($paramName, $values);

            return;
        }

        if ([] === $nonEmptyValues) {
            $qb->andWhere($qb->expr()->isNull($expr));

            return;
        }

        $paramName = \sprintf(':%s_in', str_replace('.', '_', $field));

        $qb->andWhere($qb->expr()->orX(
            \sprintf('%s IN (%s)', $expr, $paramName),
            $qb->expr()->isNull($expr),
        ));
        $qb->setParameter($paramName, $nonEmptyValues);
    }

    /**
     * Native UUID/ULID columns reject both `IN ('')` and a raw string bound with an
     * identifier Doctrine type. Keep the same skip/equality contract as the scalar
     * search strategies: drop malformed or cross-type terms, and bind survivors with
     * the field type so `ulid` / `uuid_binary` still convert to the stored representation.
     *
     * @param list<mixed> $values
     */
    private function applyUuidList(QueryBuilder $qb, string $field, array $values, string $alias, string $uuidType): void
    {
        $conditions = [];

        foreach ($values as $index => $value) {
            if (!\is_string($value) && !\is_int($value)) {
                continue;
            }

            $identifier = UuidSearchTerm::normalize((string) $value, $uuidType);
            if (null === $identifier) {
                continue;
            }

            $paramName    = \sprintf('%s_in_%d', str_replace('.', '_', $field), $index);
            $conditions[] = SearchConditionBuilder::equality($qb, $alias, $field, $identifier, $paramName, $uuidType);
        }

        if ([] === $conditions) {
            return;
        }

        $qb->andWhere($qb->expr()->orX(...$conditions));
    }

    /**
     * Integer, float, date, and boolean columns reject a raw string IN list the same way
     * UUID columns do: PostgreSQL raises `invalid input syntax` / `operator does not exist`,
     * MySQL coerces garbage to 0/false and matches the wrong rows, and Doctrine date types
     * throw when converting a string. Bind each surviving value with the field type.
     *
     * Empty strings still mean "no value assigned" (IS NULL), matching the text IN path.
     * Other unbindable terms are dropped rather than crashing the Ajax request.
     *
     * @param list<mixed>                                             $values
     * @param callable(string): (string|\DateTimeImmutable|bool|null) $normalize
     */
    private function applyTypedEqualityList(
        QueryBuilder $qb,
        string $field,
        array $values,
        string $alias,
        string $doctrineType,
        callable $normalize,
    ): void {
        $conditions  = [];
        $includeNull = false;
        $expr        = RelationFieldResolver::resolve($qb, $alias, $field);

        foreach ($values as $index => $value) {
            if ('' === $value) {
                $includeNull = true;

                continue;
            }

            if (!\is_string($value) && !\is_int($value) && !\is_float($value) && !\is_bool($value)) {
                continue;
            }

            $normalized = $normalize(match (true) {
                \is_bool($value) => $value ? '1' : '0',
                default          => (string) $value,
            });

            if (null === $normalized) {
                continue;
            }

            $paramName    = \sprintf('%s_in_%d', str_replace('.', '_', $field), $index);
            $conditions[] = \sprintf('%s = :%s', $expr, $paramName);
            $qb->setParameter($paramName, $normalized, $doctrineType);
        }

        if ([] === $conditions && !$includeNull) {
            return;
        }

        if ([] === $conditions) {
            $qb->andWhere($qb->expr()->isNull($expr));

            return;
        }

        $or = $qb->expr()->orX(...$conditions);
        if ($includeNull) {
            $or->add($qb->expr()->isNull($expr));
        }

        $qb->andWhere($or);
    }

    private function applyScalar(QueryBuilder $qb, QueryFilterContext $context, ColumnControlIntent $control, ColumnInterface $column): void
    {
        $strategy = $this->registry->get($control->logic->value);
        $search   = new ColumnControlSearch($control->value ?? '', $control->logic, $control->valueType);

        $strategy->apply($qb, $column, $search, $context->nextParamIndex(), $context->alias);
    }
}
