<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Builds a QueryFilterContext from a request and configured columns through the real
 * intent factory, so filter tests exercise the same normalized intent the pipeline uses.
 *
 * @internal
 */
trait BuildsQueryFilterContext
{
    /** @var list<array{string, string, ?string, ?string}> */
    private array $capturedJoins = [];

    /**
     * @param list<ColumnInterface> $columns
     */
    private function context(DataTableRequest $request, array $columns): QueryFilterContext
    {
        $intent = (new DefaultDataTableQueryIntentFactory())->create($request, $columns);

        $columnsByName = [];
        foreach ($columns as $column) {
            $columnsByName[$column->getName()] = $column;
        }

        return new QueryFilterContext($intent, $columnsByName, 'e');
    }

    /**
     * Context for a single configured column, optionally absent from the request payload.
     */
    private function singleColumnContext(
        ColumnInterface $column,
        ?Search $search = null,
        ?ColumnControl $columnControl = null,
        bool $inRequest = true,
    ): QueryFilterContext {
        $name = $column->getName();

        $requestColumns = $inRequest
            ? [$name => new Column($name, $name, true, true, $search, $columnControl)]
            : [];

        return $this->context(new DataTableRequest(1, new Columns($requestColumns)), [$column]);
    }

    /**
     * Query builder that reports back the joins it has been given, so a filter resolving a
     * field path sees the aliases an earlier addSearchJoin() registered and reuses them
     * instead of joining the same relation twice. A mock answering getDQLPart('join') with a
     * fixed list cannot show that, since the two mechanisms only meet through that part.
     *
     * Recorded joins are readable through {@see self::capturedJoins()}.
     */
    private function joinRecordingQueryBuilder(): MockObject&QueryBuilder
    {
        $this->capturedJoins = [];

        $qb = $this->createMock(QueryBuilder::class);

        $qb->method('getDQLPart')->willReturnCallback(function (string $part): array {
            if ('join' !== $part || [] === $this->capturedJoins) {
                return [];
            }

            $joins = [];

            foreach ($this->capturedJoins as [$join, $alias]) {
                $mock = $this->createMock(Join::class);
                $mock->method('getJoin')->willReturn($join);
                $mock->method('getAlias')->willReturn($alias);

                $joins[] = $mock;
            }

            return ['e' => $joins];
        });

        $qb->method('leftJoin')->willReturnCallback(
            function (string $join, string $alias, ?string $conditionType = null, ?string $condition = null) use ($qb): QueryBuilder {
                $this->capturedJoins[] = [$join, $alias, $conditionType, $condition];

                return $qb;
            }
        );

        return $qb;
    }

    /**
     * @return list<array{string, string, ?string, ?string}>
     */
    private function capturedJoins(): array
    {
        return $this->capturedJoins;
    }

    /**
     * Query builder whose root entity maps $field neither as a scalar nor as an association,
     * as it is for a virtual column assembled in mapRow(). Filters must skip such a column:
     * emitting "<alias>.<field>" makes Doctrine reject the whole query.
     */
    private function unmappedFieldQueryBuilder(string $field): MockObject&QueryBuilder
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with($field)->willReturn(false);
        $metadata->method('hasField')->with($field)->willReturn(false);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }

    /**
     * Query builder whose root entity maps $field to an association, so filters must refuse
     * to build a predicate on it until the configuration points at an explicit scalar path.
     */
    private function associationFieldQueryBuilder(string $field): MockObject&QueryBuilder
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with($field)->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }
}
