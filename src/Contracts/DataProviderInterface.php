<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;

/**
 * Resolves one page of rows for a parsed DataTables request, already mapped to arrays.
 *
 * Implementations must honor the request's pagination, ordering, and search, and must return
 * recordsTotal (before filtering) and recordsFiltered (after) so the client can page correctly.
 * Row mapping belongs to a {@see RowMapperInterface} the provider is given, not to the provider:
 * pass AbstractDataTable::createRowMapper() so template columns and action URLs keep resolving.
 *
 * The bundle obtains a provider from AbstractDataTable::createDataProvider(); when that returns
 * null and the class carries #[AsDataTable], AutoDataProviderFactory builds a
 * {@see \Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider} instead. Implement this
 * only for a non-Doctrine backend -- for a Doctrine table, customizeQueryBuilder() is the seam.
 */
interface DataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult;
}
