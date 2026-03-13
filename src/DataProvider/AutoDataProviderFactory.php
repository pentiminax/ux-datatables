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

    public function setEntityManager(?EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    /**
     * @param callable(QueryBuilder, DataTableRequest):QueryBuilder $queryBuilderConfigurator
     */
    public function create(
        ?AsDataTable $asDataTable,
        RowMapperInterface $rowMapper,
        callable $queryBuilderConfigurator,
    ): ?DataProviderInterface {
        if (null === $asDataTable) {
            return null;
        }

        if (null === $this->em) {
            throw new \LogicException('EntityManagerInterface is required to auto-configure a DoctrineDataProvider from #[AsDataTable]. Inject it via the runtime factory or setEntityManager().');
        }

        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: $asDataTable->entityClass,
            rowMapper: $rowMapper,
            queryBuilderConfigurator: $queryBuilderConfigurator,
        );
    }
}
