<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

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

    /**
     * @param callable(QueryBuilder, DataTableRequest):QueryBuilder $configureQueryBuilder
     */
    public function resolve(
        ?DataProviderInterface $manualDataProvider,
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $configureQueryBuilder,
    ): ?DataProviderInterface {
        return $manualDataProvider ?? $this->autoDataProviderFactory->create($asDataTable, $rowMapper, $configureQueryBuilder);
    }
}
