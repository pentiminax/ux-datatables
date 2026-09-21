<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;

/**
 * One stage of the server-side query pipeline, translating part of a normalized read intent into
 * Doctrine conditions.
 *
 * Implementations read criteria from {@see QueryFilterContext} -- its DataTableQueryIntent, its
 * name-indexed columns, and its root alias -- and must not re-parse the raw request. They must be
 * a no-op when their part of the intent is absent, must
 * draw every Doctrine parameter name from the context's index helpers so filters cannot collide,
 * and must only add conditions: the intent's offset and limit are applied by the provider, not
 * here.
 *
 * {@see \Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline} owns the shipped chain
 * and is not extensible through a tag. For per-table conditions, override
 * AbstractDataTable::customizeQueryBuilder(); implement this interface only to reuse a filter
 * across several tables, and call it from there.
 */
interface QueryFilterInterface
{
    public function apply(QueryBuilder $qb, QueryFilterContext $context): void;
}
