<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Free-text filter applying a case-insensitive LIKE %value% condition.
 */
final class TextFilter extends AbstractFilter
{
    protected function getType(): string
    {
        return 'text';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        if (!\is_string($value) || '' === trim($value)) {
            return;
        }

        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        $param = $this->parameterName();

        $qb->andWhere(\sprintf('LOWER(%s) LIKE :%s', $expr, $param));
        $qb->setParameter($param, '%'.mb_strtolower(trim($value)).'%');
    }
}
