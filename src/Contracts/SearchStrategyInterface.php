<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;

interface SearchStrategyInterface
{
    /**
     * Apply search logic to the QueryBuilder for a specific column.
     */
    public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void;

    public function getLogic(): string;
}
