<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

final class DataProviderResolver
{
    public function __construct(
        private readonly AutoDataProviderFactory $autoDataProviderFactory,
    ) {
    }

    public function setEntityManager(?EntityManagerInterface $em): void
    {
        $this->autoDataProviderFactory->setEntityManager($em);
    }

    /**
     * @param callable(QueryBuilder, DataTableRequest):QueryBuilder $queryBuilderConfigurator
     */
    public function resolve(
        ?DataProviderInterface $manualDataProvider,
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $queryBuilderConfigurator,
    ): ?DataProviderInterface {
        return $manualDataProvider ?? $this->autoDataProviderFactory->create($asDataTable, $rowMapper, $queryBuilderConfigurator);
    }
}
