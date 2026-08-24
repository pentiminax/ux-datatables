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

        // A to-many join added by configureBaseQueryBuilder() (permanent business-rule
        // scoping, e.g. an active-only filter joining a relation) would inflate a plain
        // COUNT(e) the same way a searchable join does for $filteredCountQb below --
        // COUNT(DISTINCT e) counts distinct root entities instead.
        $recordsTotal = (int) (clone $baseQb)
            ->select("COUNT(DISTINCT $alias)")
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

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
        // Note: this assumes a single-column primary key (Doctrine resolves COUNT(DISTINCT e) to
        // the first identifier column); entities with composite keys would need a subquery over
        // the identifiers instead.
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
