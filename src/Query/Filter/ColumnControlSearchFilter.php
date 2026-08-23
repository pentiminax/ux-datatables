<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\ColumnSearchResolver;
use Pentiminax\UX\DataTables\Query\Intent\ColumnControlIntent;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\SearchConditionBuilder;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\UuidSearchTerm;

/**
 * Filter that applies column-control search criteria using the strategy pattern.
 *
 * Consumes the normalized {@see ColumnControlIntent} criteria. List criteria are
 * applied through an explicit IN branch on text columns; native UUID/ULID columns
 * expand to typed equalities so invalid terms never reach setParameter() and
 * binary identifier types still match. Scalar criteria delegate to the registered
 * search strategy for their logic.
 *
 * Per-column, both branches respect search joins declared via
 * {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::addSearchJoin()} and the
 * effective field override from
 * {@see \Pentiminax\UX\DataTables\Column\AbstractColumn::setSearchField()}.
 *
 * Note: the {@see \Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface}
 * custom predicate is intentionally not invoked here. Column-control strategies
 * encode their own predicate shapes (IN, comparison, nullness) that a generic
 * open-ended closure cannot safely compose. The Contains strategy does delegate
 * to the custom predicate via SearchPredicateFactory.
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

            // Apply any column-declared search joins before resolving predicates.
            ColumnSearchResolver::applySearchJoins($qb, $column);

            // Resolve the effective field path (setSearchField() > getField() > reference fieldPath).
            $field = ColumnSearchResolver::resolveField($column) ?? $control->column->fieldPath;

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

            $this->applyScalar($qb, $context, $control, $field);
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

        $uuidType = RelationFieldResolver::resolveUuidFieldType($qb, $field);
        if (null !== $uuidType) {
            $this->applyUuidList($qb, $field, $values, $alias, $uuidType);

            return;
        }

        $expr      = RelationFieldResolver::resolve($qb, $alias, $field);
        $paramName = \sprintf(':%s_in', str_replace('.', '_', $field));

        $qb->andWhere(\sprintf('%s IN (%s)', $expr, $paramName));
        $qb->setParameter($paramName, $values);
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

    private function applyScalar(QueryBuilder $qb, QueryFilterContext $context, ColumnControlIntent $control, string $field): void
    {
        $column = $context->columnByName($control->column->name);
        if (null === $column) {
            return;
        }

        $strategy = $this->registry->get($control->logic->value);
        $search   = new ColumnControlSearch($control->value ?? '', $control->logic, $control->valueType);

        $strategy->apply($qb, $column, $search, $context->nextParamIndex(), $context->alias);
    }
}
