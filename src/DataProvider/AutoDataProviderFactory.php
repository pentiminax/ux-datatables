<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

final class AutoDataProviderFactory
{
    public function __construct(
        private ?EntityManagerInterface $em = null,
    ) {
    }

    /**
     * @param callable(QueryBuilder, DataTableRequest):QueryBuilder $configureQueryBuilder
     */
    public function create(
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $configureQueryBuilder,
        ?\Closure $pageProjector = null,
    ): ?DataProviderInterface {
        if (null === $asDataTable) {
            return null;
        }

        if (null === $this->em) {
            throw new \LogicException('EntityManagerInterface is required to auto-configure a DoctrineDataProvider from #[AsDataTable]. Ensure Doctrine ORM is installed and the DataTable is managed by Symfony.');
        }

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: $asDataTable->entityClass,
            rowMapper: $rowMapper,
            configureQueryBuilder: $configureQueryBuilder,
            pageProjector: $pageProjector,
        );
    }
}
