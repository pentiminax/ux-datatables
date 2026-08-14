<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query\Strategy;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Pentiminax\UX\DataTables\Query\UuidSearchTerm;

/**
 * Parameterized search strategy for simple comparison operators.
 *
 * Replaces individual strategy classes (Equal, NotEqual, StartsWith, etc.)
 * that differ only in their SQL operator and parameter wrapping format.
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

        $fieldPath = $column->getField();
        if (null === $fieldPath) {
            return;
        }

        if ($this->logic->usesLikeOperator() && !RelationFieldResolver::supportsTextSearch($qb, $fieldPath)) {
            return;
        }

        $uuidType = RelationFieldResolver::resolveUuidFieldType($qb, $fieldPath);
        $value    = $search->value;

        if (null !== $uuidType) {
            $value = UuidSearchTerm::normalize($value);

            if (null === $value) {
                return;
            }
        }

        $field     = RelationFieldResolver::resolve($qb, $alias, $fieldPath);
        $paramName = \sprintf('column_control_param_%d', $paramIndex);

        $qb->andWhere(\sprintf('%s %s :%s', $field, $this->logic->operator(), $paramName));
        $qb->setParameter($paramName, \sprintf($this->logic->paramFormat(), $value), $uuidType);
    }

    public function getLogic(): string
    {
        return $this->logic->value;
    }
}
