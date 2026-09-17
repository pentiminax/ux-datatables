<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\DateSearchTerm;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Date range filter with optional "from" and "to" bounds.
 *
 * The submitted value is an associative array {from?: string, to?: string}.
 * `from` is inclusive (`>=`). A date-only `to` on a datetime column includes the
 * selected calendar day via an exclusive next-midnight comparison (`<`); a
 * time-bearing `to`, and any `to` on a date column, stay inclusive (`<=`).
 *
 * Native Doctrine date/time columns bind a parsed {@see \DateTimeImmutable} with the
 * field's type. A raw string makes Doctrine conversion throw, and PostgreSQL rejects
 * `timestamp >= text`. Unparseable bounds are skipped instead of crashing the Ajax
 * request, matching {@see DateSearchTerm}.
 */
final class DateRangeFilter extends AbstractFilter
{
    /**
     * Doctrine types that store a time of day. A date-only HTML input normalizes to
     * midnight, so an inclusive `<=` would drop every later timestamp on that day.
     *
     * @var list<string>
     */
    private const array DATETIME_FIELD_TYPES = [
        'datetime',
        'datetime_immutable',
        'datetimetz',
        'datetimetz_immutable',
    ];

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

        $dateType = RelationFieldResolver::resolveDateFieldType($qb, $this->resolvedField());
        $from     = $this->normalizeBound($value['from'] ?? null, $dateType);
        $to       = $this->normalizeBound($value['to'] ?? null, $dateType);

        if (null !== $from) {
            $param = $this->parameterName('from');
            $qb->andWhere(\sprintf('%s >= :%s', $expr, $param));
            $this->bindBound($qb, $param, $from, $dateType);
        }

        if (null !== $to) {
            $param       = $this->parameterName('to');
            $rawTo       = \is_string($value['to'] ?? null) ? trim($value['to']) : '';
            $exclusiveTo = $this->exclusiveNextDayUpperBound($rawTo, $to, $dateType);

            if (null !== $exclusiveTo) {
                $qb->andWhere(\sprintf('%s < :%s', $expr, $param));
                $this->bindBound($qb, $param, $exclusiveTo, $dateType);
            } else {
                $qb->andWhere(\sprintf('%s <= :%s', $expr, $param));
                $this->bindBound($qb, $param, $to, $dateType);
            }
        }
    }

    private function normalizeBound(mixed $value, ?string $dateType): string|\DateTimeImmutable|null
    {
        if (!\is_string($value) || '' === trim($value)) {
            return null;
        }

        $value = trim($value);

        if (null === $dateType) {
            return $value;
        }

        return DateSearchTerm::normalize($value);
    }

    private function bindBound(QueryBuilder $qb, string $param, string|\DateTimeImmutable $value, ?string $dateType): void
    {
        if (null === $dateType) {
            $qb->setParameter($param, $value);

            return;
        }

        $qb->setParameter($param, $value, $dateType);
    }

    /**
     * Date-only `to` on a datetime column is midnight of that day. Inclusive `<=`
     * would drop every later timestamp on the selected calendar day.
     */
    private function exclusiveNextDayUpperBound(string $rawTo, string|\DateTimeImmutable $to, ?string $dateType): ?\DateTimeImmutable
    {
        if (!$to instanceof \DateTimeImmutable
            || !\in_array($dateType, self::DATETIME_FIELD_TYPES, true)
            || !$this->isDateOnlyInput($rawTo)
        ) {
            return null;
        }

        return $to->add(new \DateInterval('P1D'));
    }

    private function isDateOnlyInput(string $value): bool
    {
        return 1 === preg_match('/^\d{4}[-\/]\d{1,2}[-\/]\d{1,2}$/', $value);
    }
}
