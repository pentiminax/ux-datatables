<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Equality, or IN for several values, on the filter field with bound parameters.
 *
 * A using class may bind through a Doctrine type by overriding {@see self::choiceBindingType()}:
 * each value is then normalized, the ones that fail are dropped, and several values become typed
 * equalities joined by OR.
 *
 * @internal
 */
trait AppliesChoiceCondition
{
    protected function choiceBindingType(QueryBuilder $qb): ?string
    {
        return null;
    }

    protected function normalizeChoiceValue(string $value, string $doctrineType): ?string
    {
        return $value;
    }

    private function applyChoice(QueryBuilder $qb, mixed $value, string $alias, bool $multiple): void
    {
        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        $type = $this->choiceBindingType($qb);

        if ($multiple) {
            $values = array_values(array_filter(
                \is_array($value) ? $value : [$value],
                static fn (mixed $item): bool => \is_scalar($item) && '' !== (string) $item,
            ));

            if (null !== $type) {
                $this->applyTypedChoices($qb, $expr, array_map(strval(...), $values), $type);

                return;
            }

            if ([] === $values) {
                return;
            }

            $param = $this->parameterName('in');
            $qb->andWhere(\sprintf('%s IN (:%s)', $expr, $param));
            $qb->setParameter($param, $values);

            return;
        }

        if (\is_array($value) || !\is_scalar($value) || '' === (string) $value) {
            return;
        }

        if (null !== $type) {
            $this->applyTypedChoices($qb, $expr, [(string) $value], $type);

            return;
        }

        $param = $this->parameterName();
        $qb->andWhere(\sprintf('%s = :%s', $expr, $param));
        $qb->setParameter($param, $value);
    }

    /**
     * @param list<string> $values
     */
    private function applyTypedChoices(QueryBuilder $qb, string $expr, array $values, string $type): void
    {
        $conditions = [];

        foreach ($values as $value) {
            $normalized = $this->normalizeChoiceValue($value, $type);
            if (null === $normalized) {
                continue;
            }

            $param        = $this->parameterName('in_'.\count($conditions));
            $conditions[] = \sprintf('%s = :%s', $expr, $param);
            $qb->setParameter($param, $normalized, $type);
        }

        if ([] === $conditions) {
            return;
        }

        $qb->andWhere(1 === \count($conditions) ? $conditions[0] : '('.implode(' OR ', $conditions).')');
    }
}
