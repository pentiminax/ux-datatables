<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Three-state filter (true / false / blank).
 *
 * On a boolean field the true and false states compare the column with true and false. On any
 * other field the true state matches "field IS NOT NULL" and the false state "field IS NULL".
 * Call nullable() to keep the NULL checks on a boolean field, or values() to compare the field
 * with concrete values.
 */
final class TernaryFilter extends AbstractFilter
{
    private ?string $trueLabel = null;

    private ?string $falseLabel = null;

    private mixed $trueValue = null;

    private mixed $falseValue = null;

    private bool $usesValues = false;

    private bool $nullable = false;

    public function trueLabel(string $label): static
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): static
    {
        $this->falseLabel = $label;

        return $this;
    }

    /**
     * Compare the field against concrete values instead of NULL checks.
     */
    public function values(mixed $trueValue, mixed $falseValue): static
    {
        $this->trueValue  = $trueValue;
        $this->falseValue = $falseValue;
        $this->usesValues = true;

        return $this;
    }

    /**
     * Keep the NULL checks ("has a value" / "has no value"), even on a boolean field.
     */
    public function nullable(bool $nullable = true): static
    {
        $this->nullable = $nullable;

        return $this;
    }

    /**
     * Translate the true/false labels at render time, falling back to the
     * built-in "Yes"/"No" defaults when none were set via trueLabel()/falseLabel().
     */
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void
    {
        parent::translateLabels($translator, $locale);

        $this->trueLabel  = $translator->trans($this->trueLabel ?? 'Yes', locale: $locale);
        $this->falseLabel = $translator->trans($this->falseLabel ?? 'No', locale: $locale);
    }

    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'trueLabel'  => $this->trueLabel  ?? 'Yes',
            'falseLabel' => $this->falseLabel ?? 'No',
        ];
    }

    protected function getType(): string
    {
        return 'ternary';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        $state = $this->normalizeState($value);
        if (null === $state) {
            return;
        }

        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        if ($this->usesValues) {
            $this->compareWith($qb, $expr, $state, $state ? $this->trueValue : $this->falseValue);

            return;
        }

        if (!$this->nullable && null !== RelationFieldResolver::resolveBooleanFieldType($qb, $this->resolvedField())) {
            $this->compareWith($qb, $expr, $state, $state);

            return;
        }

        $qb->andWhere(\sprintf('%s IS %s NULL', $expr, $state ? 'NOT' : ''));
    }

    private function compareWith(QueryBuilder $qb, string $expr, bool $state, mixed $value): void
    {
        $param = $this->parameterName($state ? 'true' : 'false');
        $qb->andWhere(\sprintf('%s = :%s', $expr, $param));
        $qb->setParameter($param, $value);
    }

    private function normalizeState(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (!\is_scalar($value)) {
            return null;
        }

        return match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default            => null,
        };
    }
}
