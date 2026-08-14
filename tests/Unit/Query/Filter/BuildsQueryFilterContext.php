<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
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
 * intent factory, so filter tests exercise the same normalized intent the chain uses.
 *
 * @internal
 */
trait BuildsQueryFilterContext
{
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
