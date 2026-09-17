<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

final class AutoDataProviderFactory
{
    public function __construct(
        private ?EntityManagerInterface $em = null,
        private ?ApiPlatformCollectionProviderFactory $apiPlatformProviderFactory = null,
    ) {
    }

    /**
     * @param callable(QueryBuilder, DataTableRequest):QueryBuilder      $configureQueryBuilder
     * @param callable(QueryBuilder, DataTableRequest):QueryBuilder|null $configureBaseQueryBuilder
     * @param list<ColumnInterface>                                      $columns
     */
    public function create(
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $configureQueryBuilder,
        ?RowMapperInterface $exportRowMapper = null,
        ?\Closure $pageProjector = null,
        ?callable $configureBaseQueryBuilder = null,
        bool $apiPlatform = false,
        array $columns = [],
        ?string $dataTableClass = null,
    ): ?DataProviderInterface {
        if (null === $asDataTable) {
            return null;
        }

        if ($apiPlatform && null !== $this->apiPlatformProviderFactory) {
            $apiPlatformProvider = $this->apiPlatformProviderFactory->create(
                entityClass: $asDataTable->entityClass,
                columns: $columns,
                rowMapper: $rowMapper,
                exportRowMapper: $exportRowMapper,
                dataTableClass: $dataTableClass,
            );

            // An entity with no collection operation falls through to Doctrine, the same
            // fallback the RenderingPreparer applies when it cannot resolve a collection URL.
            if (null !== $apiPlatformProvider) {
                return $apiPlatformProvider;
            }
        }

        if (null === $this->em) {
            throw new \LogicException('EntityManagerInterface is required to auto-configure a DoctrineDataProvider from #[AsDataTable]. Ensure Doctrine ORM is installed and the DataTable is managed by Symfony.');
        }

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: $asDataTable->entityClass,
            rowMapper: $rowMapper,
            configureQueryBuilder: $configureQueryBuilder,
            exportRowMapper: $exportRowMapper,
            pageProjector: $pageProjector,
            configureBaseQueryBuilder: $configureBaseQueryBuilder,
        );
    }
}
