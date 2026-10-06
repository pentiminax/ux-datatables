<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DataProvider;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\IdentifierCollectingDataProviderInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\Contracts\ScopedIdentifierProviderInterface;
use Pentiminax\UX\DataTables\Contracts\StreamingDataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Query\CollectionJoinDetector;
use Pentiminax\UX\DataTables\Query\DoctrineSortDirection;
use Pentiminax\UX\DataTables\RowMapper\RowContext;

class DoctrineDataProvider implements DataProviderInterface, IdentifierCollectingDataProviderInterface, ScopedIdentifierProviderInterface, StreamingDataProviderInterface
{
    private const int IN_SCOPE_CHUNK_SIZE = 500;

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

        $this->breakPageTiesByIdentifier($qb, $alias);

        $filteredCount = $this->isSameCountQuery($baseQb, $qb) ? $recordsTotal : $this->count($qb, $alias);

        if ($request->start > 0) {
            $qb->setFirstResult($request->start);
        }

        if ($request->length > 0) {
            $qb->setMaxResults($request->length);
        }

        $items         = $this->fetchPage($qb, $alias);
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
     * A to-many join makes LIMIT/OFFSET page joined rows, so such a query pages distinct root
     * identifiers first. GROUP BY and composite identifiers keep the plain path.
     *
     * @return list<mixed>
     */
    private function fetchPage(QueryBuilder $qb, string $alias): array
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);

        if (
            [] !== $qb->getDQLPart('groupBy')
            || $metadata->isIdentifierComposite
            || !$metadata->hasField($metadata->getSingleIdentifierFieldName())
            || !CollectionJoinDetector::joinsCollection($qb, $this->em, $alias, $this->entityClass)
        ) {
            return array_values($qb->getQuery()->getResult());
        }

        $identifier = $metadata->getSingleIdentifierFieldName();
        $ids        = $this->scopedIdentifiers($qb, $alias, $identifier);

        if ([] === $ids) {
            return [];
        }

        $type  = $this->fieldType($metadata, $identifier);
        $items = (clone $qb)
            ->setFirstResult(null)
            ->setMaxResults(null)
            ->andWhere($qb->expr()->in("$alias.$identifier", ':ux_datatables_page_ids'))
            ->setParameter('ux_datatables_page_ids', $ids, $this->arrayBindingType($type))
            ->getQuery()
            ->getResult();

        // The identifier query returns raw column values and the entities carry hydrated ones;
        // converting the entity side back to what the column holds makes the two comparable.
        $positions = array_flip(array_map(strval(...), $ids));
        $position  = fn (mixed $item): int => $positions[(string) $this->toDatabaseValue($type, $metadata->getFieldValue($this->rootEntity($item), $identifier))] ?? \PHP_INT_MAX;

        usort($items, static fn (mixed $left, mixed $right): int => $position($left) <=> $position($right));

        return $items;
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
        [$qb, $alias, $identifier] = $this->buildIdentifierScopedQuery($request);

        $ids = $this->scopedIdentifiers($qb, $alias, $identifier);

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
     * Every identifier the request covers, ignoring its own pagination.
     *
     * Same scoping as {@see self::iterateRows()} — customizeQueryBuilder(), the interactive
     * search, ordering and filters — so a bulk action over "all matching rows" acts on exactly
     * what the user was looking at.
     *
     * The selection speaks in whichever field feeds `DT_RowId`, which
     * {@see \Pentiminax\UX\DataTables\Model\BulkActions::setIdField()} may point away from the
     * primary key. Collecting the primary key instead would hand the repository lookup values from
     * another namespace.
     *
     * @return list<int|string>
     */
    public function collectIdentifiers(DataTableRequest $request, ?string $field = null): array
    {
        [$qb, $alias, $identifier] = $this->buildIdentifierScopedQuery($request, $field);

        return $this->normalizeIdentifiers($this->scopedIdentifiers($qb, $alias, $identifier));
    }

    /**
     * Which of $ids the permanent scope (customizeQueryBuilder() alone) contains; search and
     * filters are left out, since a filtered-away row is still the user's to act on.
     */
    public function filterIdentifiersInScope(DataTableRequest $request, array $ids, string $field): array
    {
        $metadata = $this->em->getClassMetadata($this->entityClass);

        if (!$metadata->hasField($field) && !\in_array($field, $metadata->getIdentifier(), true)) {
            throw new \LogicException(\sprintf('"%s" is not a mapped field of "%s", so identifiers cannot be checked against the table scope.', $field, $this->entityClass));
        }

        $type       = $this->fieldType($metadata, $field);
        $expression = $metadata->hasField($field) ? "e.$field" : "IDENTITY(e.$field)";

        $databaseValues = [];
        foreach ($ids as $id) {
            $value = $this->toDatabaseValue($type, $id);

            if (null !== $value) {
                $databaseValues[(string) $value] = $value;
            }
        }

        $inScope = [];

        foreach (array_chunk(array_values($databaseValues), self::IN_SCOPE_CHUNK_SIZE) as $chunk) {
            $qb = $this->em
                ->createQueryBuilder()
                ->select('e')
                ->from($this->entityClass, 'e');

            if ($this->configureBaseQueryBuilder) {
                $qb = ($this->configureBaseQueryBuilder)($qb, $request);
            }

            $scoped = (clone $qb)
                ->select("DISTINCT $expression")
                ->resetDQLPart('orderBy')
                ->setFirstResult(null)
                ->setMaxResults(null)
                ->andWhere($qb->expr()->in($expression, ':ux_datatables_scope_ids'))
                ->setParameter('ux_datatables_scope_ids', $chunk, $this->arrayBindingType($type));

            // A HAVING may name a computed alias, which only exists while its SELECT part does.
            foreach ($this->orderDependentSelectParts($qb, 'e') as $part) {
                $scoped->addSelect($part);
            }

            $rows = $scoped->getQuery()->getScalarResult();

            foreach ($rows as $row) {
                $column = reset($row);
                $value  = \is_object($column) ? $this->toDatabaseValue($type, $column) : $column;

                if (null !== $value) {
                    $inScope[(string) $value] = true;
                }
            }
        }

        return array_values(array_filter($ids, function (int|string $id) use ($type, $inScope): bool {
            $value = $this->toDatabaseValue($type, $id);

            return null !== $value && isset($inScope[(string) $value]);
        }));
    }

    private function fieldType(ClassMetadata $metadata, string $field): ?Type
    {
        $name = $metadata->getTypeOfField($field);

        return null === $name ? null : Type::getType($name);
    }

    /**
     * The value as the database stores it, so browser, hydrated and raw values compare equal; null
     * when the type refuses it.
     */
    private function toDatabaseValue(?Type $type, mixed $value): mixed
    {
        if (null === $type) {
            return $value;
        }

        try {
            return $type->convertToDatabaseValue($value, $this->em->getConnection()->getDatabasePlatform());
        } catch (ConversionException) {
            return null;
        }
    }

    private function arrayBindingType(?Type $type): ArrayParameterType|int
    {
        return match ($type?->getBindingType()) {
            ParameterType::INTEGER => ArrayParameterType::INTEGER,
            ParameterType::BINARY  => ArrayParameterType::BINARY,
            ParameterType::ASCII   => ArrayParameterType::ASCII,
            default                => ArrayParameterType::STRING,
        };
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<int|string>
     */
    private function normalizeIdentifiers(array $ids): array
    {
        $identifiers = [];

        foreach ($ids as $id) {
            if (\is_int($id) || \is_string($id)) {
                $identifiers[] = $id;

                continue;
            }

            if ($id instanceof \Stringable && '' !== (string) $id) {
                $identifiers[] = (string) $id;
            }
        }

        return $identifiers;
    }

    /**
     * @return array{0: QueryBuilder, 1: string, 2: string}
     */
    private function buildIdentifierScopedQuery(DataTableRequest $request, ?string $field = null): array
    {
        $alias = 'e';
        $qb    = $this->em
            ->createQueryBuilder()
            ->select($alias)
            ->from($this->entityClass, $alias);

        if ($this->configureQueryBuilder) {
            $qb = ($this->configureQueryBuilder)($qb, $request);
        }

        $metadata   = $this->em->getClassMetadata($this->entityClass);
        $identifier = null !== $field && $metadata->hasField($field)
            ? $field
            : $metadata->getSingleIdentifierFieldName();

        $this->appendIdentifierTieBreaker($qb, $alias, [$identifier]);

        return [$qb, $alias, $identifier];
    }

    /**
     * Rows tying on the sort key would otherwise repeat or vanish across pages. Unordered and
     * grouped queries are left alone.
     */
    private function breakPageTiesByIdentifier(QueryBuilder $qb, string $alias): void
    {
        if ([] === $qb->getDQLPart('orderBy') || [] !== $qb->getDQLPart('groupBy')) {
            return;
        }

        $metadata = $this->em->getClassMetadata($this->entityClass);

        $identifiers = $metadata->getIdentifierFieldNames();
        if ([] === $identifiers || array_filter($identifiers, static fn (string $name): bool => !$metadata->hasField($name))) {
            return;
        }

        $this->appendIdentifierTieBreaker($qb, $alias, $identifiers);
    }

    /**
     * @param list<string> $identifiers
     */
    private function appendIdentifierTieBreaker(QueryBuilder $qb, string $alias, array $identifiers): void
    {
        $ordered = array_map(
            static fn (mixed $order): string => (string) $order,
            $qb->getDQLPart('orderBy'),
        );

        foreach ($identifiers as $identifier) {
            foreach ($ordered as $order) {
                if (str_starts_with($order, "$alias.$identifier ")) {
                    continue 2;
                }
            }

            $qb->addOrderBy("$alias.$identifier", DoctrineSortDirection::from('asc'));
        }
    }

    /**
     * @return list<mixed>
     */
    private function scopedIdentifiers(QueryBuilder $qb, string $alias, string $identifier): array
    {
        $ids = $this->collectExportIdentifiers($qb, $alias, $identifier);

        // A LIMIT/OFFSET set by customizeQueryBuilder() caps the result itself; applied here it
        // counts root entities, where the query's own LIMIT would have counted joined SQL rows.
        return \array_slice($ids, $qb->getFirstResult() ?? 0, $qb->getMaxResults());
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

        // Compared as exact strings: SORT_REGULAR compares numeric strings as numbers, which would
        // merge the distinct VARCHAR keys "01" and "1" into one and make a row unreachable.
        $unique = [];
        foreach ($ids as $id) {
            $unique[(string) $id] ??= $id;
        }

        return array_values($unique);
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
     * Identical DQL, window and parameters count the same rows, so the second COUNT of a draw can be skipped.
     */
    private function isSameCountQuery(QueryBuilder $base, QueryBuilder $filtered): bool
    {
        $base     = (clone $base)->resetDQLPart('orderBy');
        $filtered = (clone $filtered)->resetDQLPart('orderBy');

        return $base->getDQL()         === $filtered->getDQL()
            && $base->getFirstResult() === $filtered->getFirstResult()
            && $base->getMaxResults()  === $filtered->getMaxResults()
            && $this->sameParameters($base, $filtered);
    }

    /**
     * Scalars are compared strictly (a loose comparison equates '01' and '1', which a string column
     * does not); objects fall back to equality since both builders rebuild their own instances.
     */
    private function sameParameters(QueryBuilder $a, QueryBuilder $b): bool
    {
        $left  = $this->parametersByName($a);
        $right = $this->parametersByName($b);

        if (\count($left) !== \count($right)) {
            return false;
        }

        foreach ($left as $name => [$value, $type]) {
            if (!isset($right[$name]) || $right[$name][1] !== $type) {
                return false;
            }

            $other = $right[$name][0];
            if (\is_object($value) || \is_object($other) ? $value != $other : $value !== $other) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, array{mixed, mixed}>
     */
    private function parametersByName(QueryBuilder $qb): array
    {
        $parameters = [];
        foreach ($qb->getParameters() as $parameter) {
            $parameters[(string) $parameter->getName()] = [$parameter->getValue(), $parameter->getType()];
        }

        return $parameters;
    }

    /**
     * Counts distinct roots (single-column primary key only); a permanent GROUP BY counts its own
     * result rows instead.
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
