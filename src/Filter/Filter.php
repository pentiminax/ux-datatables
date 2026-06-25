<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Filter;

use Doctrine\ORM\QueryBuilder;

/**
 * Generic checkbox filter driven entirely by a user-provided query() closure.
 *
 * The closure runs only when the checkbox is checked (truthy submitted value).
 */
final class Filter extends AbstractFilter
{
    protected function getType(): string
    {
        return 'checkbox';
    }

    protected function doApply(QueryBuilder $qb, mixed $value, string $alias): void
    {
        throw new \LogicException(\sprintf('The generic filter "%s" requires a query() closure.', $this->name));
    }
}
