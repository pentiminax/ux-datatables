<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;

class DoctrineDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $entityClass,
        private readonly RowMapperInterface $rowMapper,
        /** @var callable(QueryBuilder, DataTableRequest):QueryBuilder|null */
        private $configureQueryBuilder = null,
    ) {
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $alias = 'e';

        $countQb = $this->em
            ->createQueryBuilder()
            ->select("COUNT($alias)")
            ->from($this->entityClass, $alias);

        $recordsTotal = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb = $this->em
            ->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        if ($this->configureQueryBuilder) {
            $qb = ($this->configureQueryBuilder)($qb, $request);
        }

        $filteredCountQb = clone $qb;
        $filteredCount   = (int) $filteredCountQb
            ->select("COUNT($alias)")
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        if ($request->start) {
            $qb->setFirstResult($request->start);
        }

        if ($request->length > 0) {
            $qb->setMaxResults($request->length);
        }

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
