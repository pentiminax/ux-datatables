<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\RelationFieldResolver;

/**
 * Free-text filter applying a case-insensitive LIKE %value% condition.
 *
 * Fields whose Doctrine type cannot be used with LIKE, such as native uuid columns,
 * are skipped: PostgreSQL rejects both LOWER(uuid) and uuid LIKE.
 */
final class TextFilter extends AbstractFilter
{
    protected function getType(): string
    {
        return 'text';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        if (!\is_string($value) || '' === trim($value)) {
            return;
        }

        if (!RelationFieldResolver::supportsTextSearch($qb, $this->resolvedField())) {
            return;
        }

        $expr = $this->resolveExpression($qb, $alias);
        if (null === $expr) {
            return;
        }

        $param = $this->parameterName();

        $qb->andWhere(\sprintf('LOWER(%s) LIKE :%s', $expr, $param));
        $qb->setParameter($param, '%'.mb_strtolower(trim($value)).'%');
    }
}
