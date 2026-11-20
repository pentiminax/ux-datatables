<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\VisibleColumns;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableHooksInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Runtime\TableDefinition;

final class AutoDataProviderFactory
{
    public function __construct(
        private ?EntityManagerInterface $em = null,
        private ?ApiPlatformCollectionProviderFactory $apiPlatformProviderFactory = null,
    ) {
    }

    /**
     * @param VisibleColumns $visibleColumns the permission-filtered columns the provider queries with
     */
    public function create(
        TableDefinition $definition,
        DataTableHooksInterface $hooks,
        RowMapperInterface $rowMapper,
        QueryFilterPipeline $queryFilterPipeline,
        VisibleColumns $visibleColumns,
        ?RowMapperInterface $exportRowMapper = null,
    ): ?DataProviderInterface {
        $asDataTable    = $definition->asDataTable;
        $columns        = $visibleColumns->columns;
        $apiPlatform    = $definition->optIns->optsIntoApiPlatform();
        $dataTableClass = $definition->class;
        $pageProjector  = $hooks->projectPage(...);

        if (null === $asDataTable) {
            return null;
        }

        if ($apiPlatform && null !== $this->apiPlatformProviderFactory) {
            // projectPage() is a table-level contract, not a Doctrine one. Template columns
            // and DTO mapping still run when this collection is read through API Platform.
            $apiPlatformProvider = $this->apiPlatformProviderFactory->create(
                entityClass: $asDataTable->entityClass,
                columns: $columns,
                rowMapper: $rowMapper,
                exportRowMapper: $exportRowMapper,
                dataTableClass: $dataTableClass,
                pageProjector: $pageProjector,
                visibleColumns: $visibleColumns,
            );

            // A table in ApiPlatformUnavailable mode refuses before reaching here; one with an
            // explicit ajax() still falls through until #65 settles that combination.
            if (null !== $apiPlatformProvider) {
                return $apiPlatformProvider;
            }
        }

        if (null === $this->em) {
            throw new \LogicException('EntityManagerInterface is required to auto-configure a DoctrineDataProvider from #[AsDataTable]. Ensure Doctrine ORM is installed and the DataTable is managed by Symfony.');
        }

        // `entityClass` accepts any class, so a table fed from a DTO reaches here with nothing for
        // Doctrine to query. Saying so beats the mapping error the query builder would raise.
        if ($this->em->getMetadataFactory()->isTransient($asDataTable->entityClass)) {
            throw new \LogicException(\sprintf('The entity class "%s" declared on #[AsDataTable] is not mapped by Doctrine, so no query can be built from it. Declare a mapped entity through "entityClass", or provide the rows yourself through createDataProvider().', $asDataTable->entityClass));
        }

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: $asDataTable->entityClass,
            rowMapper: $rowMapper,
            configureQueryBuilder: static fn (QueryBuilder $qb, DataTableRequest $request): QueryBuilder => $queryFilterPipeline->apply(
                qb: $hooks->customizeQueryBuilder($qb, $request),
                request: $request,
                columns: $columns,
                filters: $definition->filters,
                registry: $hooks->createSearchStrategyRegistry(),
                predicateBuilder: $hooks->createSearchPredicateBuilder(),
            ),
            exportRowMapper: $exportRowMapper,
            pageProjector: $pageProjector,
            configureBaseQueryBuilder: $hooks->customizeQueryBuilder(...),
            columns: $columns,
            // A client-side table sums its rows in the browser, and its hydration drops the result.
            summarize: $definition->optIns->serverSide,
        );
    }
}
