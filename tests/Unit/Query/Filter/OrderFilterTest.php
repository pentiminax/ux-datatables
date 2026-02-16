<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\Query\Filter\OrderFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use PHPUnit\Framework\TestCase;

class OrderFilterTest extends TestCase
{
    public function testApplyOrderOnSimpleField(): void
    {
        $filter = new OrderFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with('e.name', 'asc');

        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);
        $order         = new Order(0, 'asc', 'name');

        $request = new DataTableRequest(1, $columns, order: [$order]);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testApplyOrderUsesAbstractColumnFieldNotRequestColumnName(): void
    {
        $filter = new OrderFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with('e.customField', 'desc');

        $column        = TextColumn::new('displayName', 'Display Name')->setField('customField');
        $requestColumn = new Column('displayName', 'displayName', true, true);
        $columns       = new Columns(['displayName' => $requestColumn]);
        $order         = new Order(0, 'desc', 'displayName');

        $request = new DataTableRequest(1, $columns, order: [$order]);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testApplyOrderWithDotNotationField(): void
    {
        $filter = new OrderFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.author', 'author')
            ->willReturn($qb);

        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with('author.firstName', 'asc');

        $column        = TextColumn::new('authorName', 'Author')->setField('author.firstName');
        $requestColumn = new Column('authorName', 'authorName', true, true);
        $columns       = new Columns(['authorName' => $requestColumn]);
        $order         = new Order(0, 'asc', 'authorName');

        $request = new DataTableRequest(1, $columns, order: [$order]);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipWhenNoOrder(): void
    {
        $filter = new OrderFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('addOrderBy');

        $column  = TextColumn::new('name', 'Name')->setField('name');
        $columns = new Columns([]);
        $request = new DataTableRequest(1, $columns, order: []);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipWhenColumnIndexOutOfBounds(): void
    {
        $filter = new OrderFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('addOrderBy');

        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);
        $order         = new Order(5, 'asc', 'name');

        $request = new DataTableRequest(1, $columns, order: [$order]);
        $context = new QueryFilterContext($request, [], 'e');

        $filter->apply($qb, $context);
    }
}
