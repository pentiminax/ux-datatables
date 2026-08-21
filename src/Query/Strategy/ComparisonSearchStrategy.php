<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchAwareColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\ColumnSearchResolver;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Parameterized search strategy for simple comparison operators.
 *
 * Replaces individual strategy classes (Equal, NotEqual, StartsWith, etc.)
 * that differ only in their SQL operator and parameter wrapping format.
 *
 * Respects {@see SearchAwareColumnInterface::getSearchField()} for field resolution and
 * applies any column-declared search joins before building the predicate.
 * The {@see \Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface}
 * custom predicate is intentionally not invoked: this strategy encodes a
 * specific comparison shape that a generic open-ended closure cannot safely compose.
 */
final class ComparisonSearchStrategy implements SearchStrategyInterface
{
    public function __construct(
        private readonly ColumnControlLogic $logic,
    ) {
        if (!$this->logic->supportsComparisonStrategy()) {
            throw new \InvalidArgumentException(\sprintf('Logic "%s" is not compatible with %s.', $this->logic->value, self::class));
        }
    }

    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
    {
        if ('' === trim($search->value)) {
            return;
        }

        ColumnSearchResolver::applySearchJoins($qb, $column);

        $effectiveField = ColumnSearchResolver::resolveField($column);
        if (null === $effectiveField) {
            return;
        }

        $field     = RelationFieldResolver::resolve($qb, $alias, $effectiveField);
        $paramName = \sprintf('column_control_param_%d', $paramIndex);

        if ($this->logic->usesTextSearch()) {
            $comparison = ColumnControlLogic::NotContains === $this->logic ? '0' : '1';
            $qb->andWhere(\sprintf('UX_DATATABLES_SEARCH(%s, :%s) = %s', $field, $paramName, $comparison));
            $qb->setParameter($paramName, \sprintf($this->logic->paramFormat(), mb_strtolower(trim($search->value))));

            return;
        }

        $qb->andWhere(\sprintf('%s %s :%s', $field, $this->logic->operator(), $paramName));
        $qb->setParameter($paramName, \sprintf($this->logic->paramFormat(), $search->value));
    }

    public function getLogic(): string
    {
        return $this->logic->value;
    }
}
