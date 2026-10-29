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

    /**
     * Only a count makes sense on any column: formatted dates and text order differently in SQL,
     * PHP and the browser.
     */
    public function requiresNumericColumn(): bool
    {
        return self::Count !== $this;
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
     * Aggregates in memory. NULL is ignored, as SQL ignores it; Count counts every non-null value.
     *
     * @param list<mixed> $values
     *
     * @internal
     */
    public function aggregate(array $values): int|float|null
    {
        $present = array_filter($values, static fn (mixed $value): bool => null !== $value);

        if (self::Count === $this) {
            return \count($present);
        }

        $numbers = array_map(static fn (mixed $value): int|float => $value + 0, array_values(array_filter($present, is_numeric(...))));

        if ([] === $numbers) {
            return null;
        }

        return match ($this) {
            self::Sum     => array_sum($numbers),
            self::Average => array_sum($numbers) / \count($numbers),
            self::Min     => min($numbers),
            self::Max     => max($numbers),
        };
    }
}
