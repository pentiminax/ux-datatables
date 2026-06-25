<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\TranslatableFilterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Three-state filter (true / false / blank).
 *
 * By default the true state matches "field IS NOT NULL" and the false state
 * "field IS NULL". Provide explicit scalar values via trueValue()/falseValue()
 * to compare against a concrete value (e.g. a boolean column) instead.
 */
final class TernaryFilter extends AbstractFilter implements TranslatableFilterInterface
{
    private ?string $trueLabel = null;

    private ?string $falseLabel = null;

    private mixed $trueValue = null;

    private mixed $falseValue = null;

    private bool $usesValues = false;

    public function trueLabel(string $label): self
    {
        $this->trueLabel = $label;

        return $this;
    }

    public function falseLabel(string $label): self
    {
        $this->falseLabel = $label;

        return $this;
    }

    /**
     * Compare the field against concrete values instead of NULL checks.
     */
    public function values(mixed $trueValue, mixed $falseValue): self
    {
        $this->trueValue  = $trueValue;
        $this->falseValue = $falseValue;
        $this->usesValues = true;

        return $this;
    }

    /**
     * Translate the true/false labels at render time, falling back to the
     * built-in "Yes"/"No" defaults when none were set via trueLabel()/falseLabel().
     */
    public function translateLabels(TranslatorInterface $translator, ?string $locale = null): void
    {
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
            $param = $this->parameterName($state ? 'true' : 'false');
            $qb->andWhere(\sprintf('%s = :%s', $expr, $param));
            $qb->setParameter($param, $state ? $this->trueValue : $this->falseValue);

            return;
        }

        $qb->andWhere(\sprintf('%s IS %s NULL', $expr, $state ? 'NOT' : ''));
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
