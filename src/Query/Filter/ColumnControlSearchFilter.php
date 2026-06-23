<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Query\Intent\ColumnControlIntent;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * Filter that applies column-control search criteria using the strategy pattern.
 *
 * Consumes the normalized {@see ColumnControlIntent} criteria. List criteria are
 * applied through an explicit IN branch; scalar criteria delegate to the registered
 * search strategy for their logic.
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
            $field = $control->column->fieldPath;
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
     * @param list<string> $values
     */
    private function applyList(QueryBuilder $qb, string $field, array $values, string $alias): void
    {
        if ([] === $values) {
            return;
        }

        $expr      = RelationFieldResolver::resolve($qb, $alias, $field);
        $paramName = \sprintf(':%s_in', str_replace('.', '_', $field));

        $qb->andWhere(\sprintf('%s IN (%s)', $expr, $paramName));
        $qb->setParameter($paramName, $values);
    }

    private function applyScalar(QueryBuilder $qb, QueryFilterContext $context, ColumnControlIntent $control, string $field): void
    {
        $column = $context->columnByName($control->column->name);
        if (null === $column) {
            return;
        }

        $strategy = $this->registry->get($control->logic->value);
        $search   = new ColumnControlSearch($control->value ?? '', $control->logic, $control->valueType);

        $strategy->apply($qb, $column, $search, $context->paramIndexFor($control->column), $context->alias);
    }
}
