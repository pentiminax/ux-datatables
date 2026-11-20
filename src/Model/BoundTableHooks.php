<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Model;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableHooksInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;

/**
 * The hooks of one table bound to one call the runtime makes.
 *
 * Built by {@see AbstractDataTable::bindContext()}, which can reach the hooks whatever their
 * visibility: a table keeps declaring them `protected`.
 *
 * @internal
 */
final readonly class BoundTableHooks implements DataTableHooksInterface
{
    public function __construct(
        public AbstractDataTable $table,
        private \Closure $mapRow,
        private \Closure $projectPage,
        private \Closure $customizeQueryBuilder,
        private \Closure $createDataProvider,
        private \Closure $createSearchStrategyRegistry,
        private \Closure $createSearchPredicateBuilder,
    ) {
    }

    public function mapRow(mixed $row): array
    {
        return ($this->mapRow)($row);
    }

    public function projectPage(array $items): ?array
    {
        return ($this->projectPage)($items);
    }

    public function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return ($this->customizeQueryBuilder)($qb, $request);
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        return ($this->createDataProvider)();
    }

    public function createSearchStrategyRegistry(): SearchStrategyRegistry
    {
        return ($this->createSearchStrategyRegistry)();
    }

    public function createSearchPredicateBuilder(): SearchPredicateBuilderInterface
    {
        return ($this->createSearchPredicateBuilder)();
    }
}
