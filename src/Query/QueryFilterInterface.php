<?php

namespace Pentiminax\UX\DataTables\Query;

use Doctrine\ORM\QueryBuilder;

/**
 * Interface for filters that can be applied to a QueryBuilder.
 *
 * Filters are applied in a chain to build complex query conditions
 * for ordering, searching, and filtering DataTable results.
 */
interface QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void;
}
