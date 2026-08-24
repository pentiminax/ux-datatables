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
        /**
         * Applied to recordsTotal's count query. Unlike $configureQueryBuilder (the app's
         * customizeQueryBuilder() plus the bundle's own interactive search/order/filter
         * pipeline), this never includes request-driven search terms -- only the app's own
         * permanent scoping (customizeQueryBuilder() alone), matching what DataTables expects
         * recordsTotal to mean: the size of the developer's own base dataset, before the
         * user's interactive search narrows it further.
         *
         * @var callable(QueryBuilder, DataTableRequest):QueryBuilder|null
         */
        private $configureBaseQueryBuilder = null,
    ) {
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        $alias = 'e';

        $baseQb = $this->em
            ->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        if ($this->configureBaseQueryBuilder) {
            $baseQb = ($this->configureBaseQueryBuilder)($baseQb, $request);
        }

        $recordsTotal = $this->count($baseQb, $alias);

        $qb = $this->em
            ->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        if ($this->configureQueryBuilder) {
            $qb = ($this->configureQueryBuilder)($qb, $request);
        }

        $filteredCount = $this->count($qb, $alias);

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

    /**
     * A permanent GROUP BY/HAVING added by customizeQueryBuilder() (e.g. grouping by a related
     * entity and using HAVING to filter groups by an aggregate condition) already collapses the
     * dataset into one row per qualifying group. Re-selecting COUNT(DISTINCT $alias) on top of
     * that GROUP BY still returns one count per group, so getSingleScalarResult() throws
     * NonUniqueResultException once there is more than one group. In that case the number of
     * groups the query already yields IS the correct total, so the count is the row count of the
     * query's own result instead of a re-selected aggregate.
     *
     * A to-many join (searchable or permanent) would inflate a plain COUNT($alias) by row
     * multiplication when there's no GROUP BY; COUNT(DISTINCT $alias) counts distinct root
     * entities instead. This assumes a single-column primary key (Doctrine resolves
     * COUNT(DISTINCT $alias) to the first identifier column); entities with composite keys would
     * need a subquery over the identifiers instead.
     */
    private function count(QueryBuilder $qb, string $alias): int
    {
        $qb = (clone $qb)->resetDQLPart('orderBy');

        if ([] !== $qb->getDQLPart('groupBy')) {
            return \count($qb->getQuery()->getScalarResult());
        }

        return (int) $qb
            ->select("COUNT(DISTINCT $alias)")
            ->getQuery()
            ->getSingleScalarResult();
    }
}
