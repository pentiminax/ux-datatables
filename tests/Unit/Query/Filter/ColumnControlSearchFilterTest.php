<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Filter\ColumnControlSearchFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\InListSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnControlSearchFilter::class)]
final class ColumnControlSearchFilterTest extends TestCase
{
    #[Test]
    public function it_skips_strategy_search_when_field_requires_an_explicit_scalar_path(): void
    {
        $strategy = $this->createMock(SearchStrategyInterface::class);
        $strategy->expects($this->never())->method('apply');

        $filter = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $strategy));
        $qb     = $this->createAssociationFieldQueryBuilder('client');

        $column        = TextColumn::new('client', 'Client');
        $columnControl = new ColumnControl(
            search: new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text')
        );
        $requestColumn = new Column('client', 'client', true, true, columnControl: $columnControl);
        $request       = new DataTableRequest(1, new Columns(['client' => $requestColumn]));
        $context       = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_skips_list_search_when_field_requires_an_explicit_scalar_path(): void
    {
        $filter = new ColumnControlSearchFilter(new SearchStrategyRegistry([new InListSearchStrategy()]));
        $qb     = $this->createAssociationFieldQueryBuilder('client');

        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('leftJoin');

        $column        = TextColumn::new('client', 'Client');
        $columnControl = new ColumnControl(list: ['acme']);
        $requestColumn = new Column('client', 'client', true, true, columnControl: $columnControl);
        $request       = new DataTableRequest(1, new Columns(['client' => $requestColumn]));
        $context       = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_delegates_search_strategy_for_supported_field(): void
    {
        $search = new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text');
        $qb     = $this->createMock(QueryBuilder::class);

        $strategy = $this->createMock(SearchStrategyInterface::class);
        $strategy->expects($this->once())
            ->method('apply')
            ->with($this->identicalTo($qb), $this->isInstanceOf(TextColumn::class), $search, 0, 'e');

        $filter = new ColumnControlSearchFilter(new SearchStrategyRegistry([], $strategy));

        $column        = TextColumn::new('clientName', 'Client')->setField('client.name');
        $columnControl = new ColumnControl(search: $search);
        $requestColumn = new Column('clientName', 'clientName', true, true, columnControl: $columnControl);
        $request       = new DataTableRequest(1, new Columns(['clientName' => $requestColumn]));
        $context       = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    private function createAssociationFieldQueryBuilder(string $field): QueryBuilder
    {
        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with($field)->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);

        return $qb;
    }
}
