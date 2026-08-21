<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Free-text filter applying a case-insensitive LIKE %value% condition.
 *
 * Delegates to the UX_DATATABLES_SEARCH DQL function which handles
 * platform-specific casting (CAST AS TEXT on PostgreSQL) and LOWER internally.
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

        $qb->andWhere(\sprintf('UX_DATATABLES_SEARCH(%s, :%s) = 1', $expr, $param));
        $qb->setParameter($param, '%'.mb_strtolower(trim($value)).'%');
    }
}
