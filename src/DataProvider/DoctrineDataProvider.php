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
     * Walks distinct root identifiers once, then loads them back in chunks.
     *
     * toIterable() cannot serve an export whose DQL joins a to-many association: it throws
     * QueryException on a fetch-joined collection, and on a plain LEFT JOIN added only to search
     * or filter (a searchable `tags.label` column is enough) it yields the same root once per
     * joined row. Either way the export breaks after the download headers were already sent, or
     * writes duplicated rows. Reading the identifiers first and re-loading them through
     * getResult() gives the uniqueness fetchData() already has.
     *
     * The identifier is appended to the ORDER BY: a user ordering on a non-unique column (a
     * status, a date) leaves ties whose relative order the database is free to change between
     * two queries, which would let a row be exported twice or not at all.
     *
     * A computed column ordered through {@see \Pentiminax\UX\DataTables\Contracts\ColumnInterface::getOrderExpression()}
     * lives as a HIDDEN SELECT alias. The identifier query keeps those extra SELECT parts so
     * `ORDER BY invoiceCount` stays valid; dropping them made Doctrine reject the export after
     * download headers were already sent.
     *
     * Chunks are loaded through `WHERE id IN (...)` rather than LIMIT/OFFSET: paginating with a
     * growing offset makes the database re-scan the skipped rows on every chunk, and a concurrent
     * insert or delete shifts the window under the export.
     *
     * ponytail: the identifier list is held in memory for the whole export (about 8 MB per 100k
     * rows). Walk the identifiers with a keyset cursor if that ceiling ever matters.
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

        $identifier = $this->em->getClassMetadata($this->entityClass)->getSingleIdentifierFieldName();

        $qb->addOrderBy("$alias.$identifier", 'ASC');

        $ids = $this->collectExportIdentifiers($qb, $alias, $identifier);

        // A LIMIT/OFFSET set by customizeQueryBuilder() caps the export itself; applied here it
        // counts root entities, where the query's own LIMIT would have counted joined SQL rows.
        $ids = \array_slice($ids, $qb->getFirstResult() ?? 0, $qb->getMaxResults());

        foreach (array_chunk($ids, max(1, $this->exportChunkSize)) as $chunk) {
            $pageQb = (clone $qb)
                ->setFirstResult(null)
                ->setMaxResults(null)
                ->andWhere($qb->expr()->in("$alias.$identifier", ':ux_datatables_export_ids'))
                ->setParameter('ux_datatables_export_ids', $chunk);

            $items = array_map($this->rootEntity(...), $pageQb->getQuery()->getResult());

            if ([] === $items) {
                continue;
            }

            yield from $this->mapChunk($items);
            $this->releaseChunk($items);
        }
    }

    /**
     * Root identifiers in the query's ORDER BY, including computed columns that exist only as
     * HIDDEN SELECT aliases.
     *
     * Replacing the SELECT with only the identifier drops those aliases, and Doctrine then
     * rejects `ORDER BY <alias>`. Fetch-joined identification variables stay out: they are not
     * needed to evaluate ORDER BY, and selecting them would make getSingleColumnResult() throw
     * {@see \Doctrine\ORM\Exception\MultipleSelectorsFoundException}.
     *
     * @return list<mixed>
     */
    private function collectExportIdentifiers(QueryBuilder $qb, string $alias, string $identifier): array
    {
        $idQb = (clone $qb)
            ->select("$alias.$identifier")
            ->setFirstResult(null)
            ->setMaxResults(null);

        $extraSelects = $this->orderDependentSelectParts($qb, $alias);
        foreach ($extraSelects as $part) {
            $idQb->addSelect($part);
        }

        $ids = [] === $extraSelects
            ? $idQb->getQuery()->getSingleColumnResult()
            : $this->identifiersFromScalarResult($idQb);

        return array_values(array_unique($ids, \SORT_REGULAR));
    }

    /**
     * SELECT parts that are not the root entity or a fetch-joined identification variable.
     *
     * @return list<string>
     */
    private function orderDependentSelectParts(QueryBuilder $qb, string $alias): array
    {
        $parts = [];

        foreach ($qb->getDQLPart('select') as $select) {
            foreach ($select->getParts() as $part) {
                $partString = (string) $part;
                if ($this->isIdentificationVariable($partString, $alias)) {
                    continue;
                }

                $parts[] = $partString;
            }
        }

        return $parts;
    }

    private function isIdentificationVariable(string $part, string $rootAlias): bool
    {
        return $part === $rootAlias || 1 === preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $part);
    }

    /**
     * The identifier is selected first, so the first scalar column is the root id.
     *
     * @return list<mixed>
     */
    private function identifiersFromScalarResult(QueryBuilder $idQb): array
    {
        $ids = [];

        foreach ($idQb->getQuery()->getScalarResult() as $row) {
            if ([] === $row) {
                continue;
            }

            $ids[] = reset($row);
        }

        return $ids;
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

        throw new \LogicException('Doctrine CSV export expected a root entity from the chunk query.');
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
