<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Equality, or IN for several values, on the filter field with bound parameters.
 *
 * @internal
 */
trait AppliesChoiceCondition
{
    private function applyChoice(QueryBuilder $qb, mixed $value, string $alias, bool $multiple): void
    {
        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        if ($multiple) {
            $values = array_values(array_filter(
                \is_array($value) ? $value : [$value],
                static fn (mixed $item): bool => \is_scalar($item) && '' !== (string) $item,
            ));

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

        $param = $this->parameterName();
        $qb->andWhere(\sprintf('%s = :%s', $expr, $param));
        $qb->setParameter($param, $value);
    }
}
