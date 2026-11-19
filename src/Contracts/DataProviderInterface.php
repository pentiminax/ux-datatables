<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
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
 *
 * supports() declares which data source features the provider applies. The bundle refuses a request
 * that uses a feature the provider does not support instead of letting it answer unfiltered:
 * SearchBuilder criteria, ColumnControl searches and configured filter values get HTTP 400, and a
 * table that declares summaries raises a \LogicException. Row scope and identifier collection are
 * declared the same way and need {@see IdentifierDataProviderInterface}. Answer every case
 * explicitly, with false for features you do not apply.
 *
 * Fail closed: a supports() that throws an \UnhandledMatchError, such as an exhaustive match lacking
 * a case, answers false for that feature.
 */
interface DataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult;

    public function supports(DataSourceFeature $feature): bool;

    /**
     * Streams every row matching the request, without pagination or a COUNT query, for server-side
     * export.
     *
     * Apply the request's search, ordering, and configured filters exactly as fetchData() does, then
     * yield rows in batches instead of materializing the whole result set: an export exists to avoid
     * holding a full table in memory.
     *
     * @return iterable<array<string, mixed>>
     */
    public function iterateRows(DataTableRequest $request): iterable;
}
