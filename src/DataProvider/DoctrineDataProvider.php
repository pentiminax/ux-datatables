<?php

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Model\DataTableQuery;
use Pentiminax\UX\DataTables\Model\DataTableResult;

class DoctrineDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $entityClass,
        private readonly RowMapperInterface $rowMapper,
        /** @var null|callable(QueryBuilder):QueryBuilder */
        private $queryBuilderConfigurator = null
    ) {
    }

    public function fetchData(DataTableQuery $query): DataTableResult
    {
        $alias = 'e';

        $countQb = $this->em
            ->createQueryBuilder()
            ->select("COUNT($alias.id)")
            ->from($this->entityClass, $alias);

        $recordsTotal = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em
            ->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        if ($this->queryBuilderConfigurator) {
            $qb = ($this->queryBuilderConfigurator)($qb);
        }

        $filteredCountQb = clone $qb;
        $filteredCount = (int) $filteredCountQb
            ->select("COUNT($alias.id)")
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->getQuery()->getResult();

        $rows = (function () use ($items) {
            foreach ($items as $item) {
                yield $this->rowMapper->map($item);
            }
        })();

        return new DataTableResult(
            recordsTotal: $recordsTotal,
            recordsFiltered: $filteredCount,
            data: $rows
        );
    }
}