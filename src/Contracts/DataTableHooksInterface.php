<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Contracts;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * The behavior a table class contributes at request time, as opposed to the configuration its
 * configure*() methods declare. {@see \Pentiminax\UX\DataTables\Model\AbstractDataTable} implements it,
 * so a table keeps overriding the same methods; the bundle runtime is the only caller.
 */
interface DataTableHooksInterface
{
    /**
     * Map one source row to the array the row pipeline then formats.
     *
     * @return array<string, mixed>
     */
    public function mapRow(mixed $row): array;

    /**
     * Transform a complete, already-paginated page of source items, or return null to disable projection.
     *
     * @param list<mixed> $items
     *
     * @return list<mixed>|null
     */
    public function projectPage(array $items): ?array;

    /**
     * Scope the query before the bundle applies the request's search, ordering, and filters.
     */
    public function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder;

    /**
     * The provider to read rows from, or null to let the bundle build one from #[AsDataTable].
     */
    public function createDataProvider(): ?DataProviderInterface;

    public function createSearchStrategyRegistry(): SearchStrategyRegistry;

    public function createSearchPredicateBuilder(): SearchPredicateBuilderInterface;
}
