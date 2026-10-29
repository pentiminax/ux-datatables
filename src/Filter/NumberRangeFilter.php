<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Model\FilterLabels;
use Pentiminax\UX\DataTables\Query\NumericSearchTerm;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Number range filter with optional "min" and "max" bounds, both inclusive.
 *
 * The submitted value is an associative array {min?: string, max?: string}. A bound that is not a
 * plain decimal number is skipped instead of failing the Ajax request, like an unparseable date in
 * {@see DateRangeFilter}. Valid bounds bind as normalized decimal strings with the field's Doctrine
 * type, as {@see NumericSearchTerm} does for column search.
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

        $field = $this->resolvedField();
        if (!RelationFieldResolver::supportsSearchFiltering($qb, $field)) {
            return;
        }

        $integerType = RelationFieldResolver::resolveIntegerFieldType($qb, $field);
        $floatType   = RelationFieldResolver::resolveFloatFieldType($qb, $field);

        $bounds = [
            'min' => $this->normalizeBound($value['min'] ?? null, true, $integerType, $floatType),
            'max' => $this->normalizeBound($value['max'] ?? null, false, $integerType, $floatType),
        ];

        $expr = null;
        foreach ($bounds as $name => $bound) {
            if (null === $bound) {
                continue;
            }

            if (false === $bound) {
                $qb->andWhere('1 = 0');

                return;
            }

            $expr ??= $this->resolveExpression($qb, $alias);
            if (null === $expr) {
                return;
            }

            [$operator, $number, $type] = $bound;
            $param                      = $this->parameterName($name);

            $qb->andWhere(\sprintf('%s %s :%s', $expr, $operator, $param));

            if (null === $type) {
                $qb->setParameter($param, $number);

                continue;
            }

            $qb->setParameter($param, $number, $type);
        }
    }

    /**
     * Keeps the validated decimal string, so no precision is lost to a PHP float. On an integer
     * column a fractional bound becomes the equivalent integer comparison (`>= 2.5` is `> 2`). A
     * bound outside the column's range is dropped when every row satisfies it, and false when none can.
     *
     * @return array{string, string, string|null}|false|null operator, number, Doctrine type to bind with
     */
    private function normalizeBound(mixed $value, bool $isMin, ?string $integerType, ?string $floatType): array|false|null
    {
        $operator = $isMin ? '>=' : '<=';

        if (\is_int($value) || \is_float($value)) {
            $value = (string) $value;
        }

        if (!\is_string($value)) {
            return null;
        }

        $value = trim($value);

        // Plain decimals only: is_numeric() also accepts exponents.
        if (1 !== preg_match('/^(?<sign>[+-]?)(?<int>\d*)(?:\.(?<fraction>\d*))?$/', $value, $matches) || 1 !== preg_match('/\d/', $value)) {
            return null;
        }

        if (null === $integerType) {
            $normalized = NumericSearchTerm::normalize($value, $floatType ?? 'float');

            return null === $normalized ? null : [$operator, $normalized, $floatType];
        }

        $negative = '-' === $matches['sign'];
        if ('' !== rtrim($matches['fraction'] ?? '', '0')) {
            $operator = $isMin ? ($negative ? '>=' : '>') : ($negative ? '<' : '<=');
        }

        $truncated  = ($negative ? '-' : '').('' === $matches['int'] ? '0' : $matches['int']);
        $normalized = NumericSearchTerm::normalize($truncated, $integerType);

        if (null === $normalized) {
            return $isMin !== $negative ? false : null;
        }

        return [$operator, $normalized, $integerType];
    }
}
