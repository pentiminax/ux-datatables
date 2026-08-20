<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;

/**
 * Default {@see SearchPredicateBuilderInterface}, delegating to {@see SearchPredicateFactory}.
 */
final class DefaultSearchPredicateBuilder implements SearchPredicateBuilderInterface
{
    public function build(
        QueryBuilder $qb,
        ColumnInterface $column,
        string $alias,
        string $field,
        string $value,
        string $paramName,
        bool $forceNumeric = false,
    ): ?string {
        return SearchPredicateFactory::build($qb, $column, $alias, $field, $value, $paramName, $forceNumeric);
    }
}
