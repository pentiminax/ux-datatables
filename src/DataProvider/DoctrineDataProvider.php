<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

class DoctrineDataProvider implements DataProviderInterface, StreamingDataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $entityClass,
        private readonly RowMapperInterface $rowMapper,
        /** @var callable(QueryBuilder, DataTableRequest):QueryBuilder|null */
        private $configureQueryBuilder = null,
        /**
         * Maps rows during an export. Defaults to $rowMapper, but the bundle builds it from the
         * exportable columns alone, so an export skips the template rendering and action
         * resolution the displayed table needs and an export file never contains.
         */
        private readonly ?RowMapperInterface $exportRowMapper = null,
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
        private readonly int $exportChunkSize = 250,
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
     * Doctrine's toIterable() cannot hydrate fetch-joined collections, so a query builder that
     * fetch-joins a to-many association is not exportable through this path.
     *
     * $pageProjector runs once per $exportChunkSize rows rather than once over the whole result
     * set: holding every row to project them together would defeat the streaming this method
     * exists for. See {@see \Pentiminax\UX\DataTables\Model\AbstractDataTable::projectPage()}
     * for what that means for a projector.
     */
    public function iterateRows(DataTableRequest $request): iterable
    {
        $alias = 'e';
        $qb    = $this->em
            ->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        if ($this->configureQueryBuilder) {
            $qb = ($this->configureQueryBuilder)($qb, $request);
        }

        $chunkSize = max(1, $this->exportChunkSize);
        $buffer    = [];

        foreach ($qb->getQuery()->toIterable() as $item) {
            $buffer[] = $this->rootEntity($item);
            if (\count($buffer) >= $chunkSize) {
                yield from $this->mapChunk($buffer);
                $this->releaseChunk($buffer);
                $buffer = [];
            }
        }

        if ([] !== $buffer) {
            yield from $this->mapChunk($buffer);
            $this->releaseChunk($buffer);
        }
    }

    /**
     * Frees a mapped chunk one entity at a time rather than through clear(). clear() empties the
     * shared EntityManager, which detaches everything the rest of the request still relies on --
     * the security token's own User included -- so any voter or lazy load running after the export
     * would fail on a detached entity. detach() only releases the roots (plus their cascade=detach
     * associations); already-loaded associations stay in the identity map, which is the accepted
     * trade-off. Narrow the selection through customizeQueryBuilder() when exporting deeply
     * associated entities.
     *
     * @param list<object> $entities
     */
    private function releaseChunk(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->em->detach($entity);
        }
    }

    /**
     * @param list<object> $items
     *
     * @return \Generator<int, array<string, mixed>>
     */
    private function mapChunk(array $items): \Generator
    {
        $items         = array_values($items);
        $pageProjector = $this->pageProjector;
        $projectedRaw  = null !== $pageProjector ? ($pageProjector)($items) : null;
        $projected     = null === $projectedRaw ? null : array_values($projectedRaw);

        if (null !== $projected && \count($projected) !== \count($items)) {
            throw new \LogicException(\sprintf('Page projector returned %d items for a source page containing %d items. Projectors must preserve page size and order.', \count($projected), \count($items)));
        }

        $rowMapper = $this->exportRowMapper ?? $this->rowMapper;

        foreach ($items as $index => $item) {
            yield $rowMapper->map(
                null === $projected ? $item : new RowContext($item, $projected[$index]),
            );
        }
    }

    private function rootEntity(mixed $item): object
    {
        if (\is_object($item)) {
            return $item;
        }

        if (\is_array($item)) {
            $candidate = $item['e'] ?? reset($item);
            if (\is_object($candidate)) {
                return $candidate;
            }
        }

        throw new \LogicException('Doctrine CSV export expected a root entity from toIterable().');
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
