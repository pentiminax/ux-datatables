<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Date range filter with optional "from" and "to" bounds.
 *
 * The submitted value is an associative array {from?: string, to?: string}.
 * Each provided bound is applied independently (>= from, <= to).
 */
final class DateRangeFilter extends AbstractFilter
{
    protected function getType(): string
    {
        return 'dateRange';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        if (!\is_array($value)) {
            return;
        }

        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        $from = $this->normalizeBound($value['from'] ?? null);
        $to   = $this->normalizeBound($value['to'] ?? null);

        if (null !== $from) {
            $param = $this->parameterName('from');
            $qb->andWhere(\sprintf('%s >= :%s', $expr, $param));
            $qb->setParameter($param, $from);
        }

        if (null !== $to) {
            $param = $this->parameterName('to');
            $qb->andWhere(\sprintf('%s <= :%s', $expr, $param));
            $qb->setParameter($param, $to);
        }
    }

    private function normalizeBound(mixed $value): ?string
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        return trim($value);
    }
}
