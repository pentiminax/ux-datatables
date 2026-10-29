<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Number range filter with optional "min" and "max" bounds, both inclusive.
 *
 * The submitted value is an associative array {min?: string, max?: string}. A bound that is not a
 * plain decimal number is skipped instead of failing the Ajax request, like an unparseable date in
 * {@see DateRangeFilter}. Valid bounds bind as PHP numbers, so integer and decimal fields compare
 * numerically.
 */
final class NumberRangeFilter extends AbstractFilter
{
    private ?string $minPlaceholder = null;

    private ?string $maxPlaceholder = null;

    public function minPlaceholder(string $placeholder): static
    {
        $this->minPlaceholder = $placeholder;

        return $this;
    }

    public function maxPlaceholder(string $placeholder): static
    {
        $this->maxPlaceholder = $placeholder;

        return $this;
    }

    /**
     * Explicit placeholders go through the default domain like labels. Unset ones fall back to
     * the bundle's own "Min" and "Max" strings.
     */
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void
    {
        parent::translateLabels($translator, $locale);

        $this->minPlaceholder = null !== $this->minPlaceholder
            ? $translator->trans($this->minPlaceholder, locale: $locale)
            : $translator->trans('filter.number.min', domain: FilterLabels::DOMAIN, locale: $locale);

        $this->maxPlaceholder = null !== $this->maxPlaceholder
            ? $translator->trans($this->maxPlaceholder, locale: $locale)
            : $translator->trans('filter.number.max', domain: FilterLabels::DOMAIN, locale: $locale);
    }

    public function jsonSerialize(): array
    {
        return array_filter([
            ...parent::jsonSerialize(),
            'minPlaceholder' => $this->minPlaceholder,
            'maxPlaceholder' => $this->maxPlaceholder,
        ], static fn (mixed $value): bool => null !== $value);
    }

    protected function getType(): string
    {
        return 'numberRange';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        if (!\is_array($value)) {
            return;
        }

        if (!RelationFieldResolver::supportsSearchFiltering($qb, $this->resolvedField())) {
            return;
        }

        $min = $this->normalizeBound($value['min'] ?? null);
        $max = $this->normalizeBound($value['max'] ?? null);

        if (null === $min && null === $max) {
            return;
        }

        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        if (null !== $min) {
            $param = $this->parameterName('min');
            $qb->andWhere(\sprintf('%s >= :%s', $expr, $param));
            $qb->setParameter($param, $min);
        }

        if (null !== $max) {
            $param = $this->parameterName('max');
            $qb->andWhere(\sprintf('%s <= :%s', $expr, $param));
            $qb->setParameter($param, $max);
        }
    }

    private function normalizeBound(mixed $value): int|float|null
    {
        if (\is_int($value) || \is_float($value)) {
            return is_finite((float) $value) ? $value : null;
        }

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        // Plain decimals only: is_numeric() also accepts exponents and leading whitespace forms.
        if (1 !== preg_match('/^[+-]?(\d+(\.\d*)?|\.\d+)$/', $value)) {
            return null;
        }

        $number = $value + 0;

        return is_finite((float) $number) ? $number : null;
    }
}
