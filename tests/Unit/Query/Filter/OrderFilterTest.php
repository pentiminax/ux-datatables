<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\Query\Filter\OrderFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OrderFilter::class)]
final class OrderFilterTest extends TestCase
{
    #[Test]
    public function it_applies_order_on_simple_field(): void
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

    #[Test]
    public function it_uses_abstract_column_field_not_request_column_name(): void
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

    #[Test]
    public function it_applies_order_with_dot_notation_field(): void
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

    #[Test]
    public function it_skips_when_no_order(): void
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

    #[Test]
    public function it_skips_when_column_index_out_of_bounds(): void
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
