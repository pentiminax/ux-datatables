<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

class DoctrineDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $entityClass,
        private readonly RowMapperInterface $rowMapper,
        /** @var callable(QueryBuilder, DataTableRequest):QueryBuilder|null */
        private $configureQueryBuilder = null,
        /** @var (\Closure(list<object>):(list<mixed>|null))|null */
        private readonly ?\Closure $pageProjector = null,
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

        // Filters supplied by configureQueryBuilder may add joins (e.g. searching over a
        // relation). When such a join traverses a to-many association, a plain COUNT(e) is
        // inflated by row multiplication. COUNT(DISTINCT e) counts distinct root entities, so
        // recordsFiltered stays correct and pagination does not break.
        $filteredCountQb = clone $qb;
        $filteredCount   = (int) $filteredCountQb
            ->select("COUNT(DISTINCT $alias)")
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        if ($request->start) {
            $qb->setFirstResult($request->start);
        }

        if ($request->length > 0) {
            $qb->setMaxResults($request->length);
        }

        $items         = array_values($qb->getQuery()->getResult());
        $pageProjector = $this->pageProjector;
        $projectedRaw  = null !== $pageProjector ? ($pageProjector)($items) : null;
        $projected     = null === $projectedRaw ? null : array_values($projectedRaw);

        if (null !== $projected && \count($projected) !== \count($items)) {
            throw new \LogicException(\sprintf('Page projector returned %d items for a source page containing %d items. Projectors must preserve page size and order.', \count($projected), \count($items)));
        }

        $rows = (function () use ($items, $projected) {
            foreach ($items as $index => $item) {
                yield $this->rowMapper->map(
                    null === $projected ? $item : new RowContext($item, $projected[$index]),
                );
            }
        })();

        return new DataTableResult(
            recordsTotal: $recordsTotal,
            recordsFiltered: $filteredCount,
            data: $rows
        );
    }
}
