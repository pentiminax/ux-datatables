<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model\Summary;

enum SummaryType: string
{
    case Sum     = 'sum';
    case Average = 'avg';
    case Min     = 'min';
    case Max     = 'max';
    case Count   = 'count';

    public function requiresNumericColumn(): bool
    {
        return self::Sum === $this || self::Average === $this;
    }

    /**
     * @internal
     */
    public function toDql(string $expression): string
    {
        return match ($this) {
            self::Sum     => "SUM($expression)",
            self::Average => "AVG($expression)",
            self::Min     => "MIN($expression)",
            self::Max     => "MAX($expression)",
            self::Count   => "COUNT($expression)",
        };
    }

    /**
     * Aggregates in memory; null and empty values are ignored, as SQL ignores NULL.
     *
     * @param list<mixed> $values
     *
     * @internal
     */
    public function aggregate(array $values): int|float|string|null
    {
        $values = array_values(array_filter(
            array_map(static fn (mixed $value): mixed => $value instanceof \DateTimeInterface ? $value->format(\DATE_ATOM) : $value, $values),
            static fn (mixed $value): bool => null !== $value && '' !== $value,
        ));

        if (self::Count === $this) {
            return \count($values);
        }

        if ([] === $values) {
            return null;
        }

        $numeric = array_filter($values, is_numeric(...));

        return match ($this) {
            self::Sum     => array_sum($numeric),
            self::Average => [] === $numeric ? null : array_sum($numeric) / \count($numeric),
            self::Min     => min($values),
            self::Max     => max($values),
        };
    }
}
