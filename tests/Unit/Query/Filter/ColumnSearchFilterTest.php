<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Filter\ColumnSearchFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use PHPUnit\Framework\TestCase;

class ColumnSearchFilterTest extends TestCase
{
    public function testApplyTextColumnSearch(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->equalTo('e.name LIKE :column_search_param_0'));

        $qb->expects($this->once())
            ->method('setParameter')
            ->with($this->equalTo('column_search_param_0'), $this->equalTo('%test%'));

        $column        = TextColumn::new('name', 'Name')->setField('name');
        $search        = new Search('test', false);
        $requestColumn = new Column('name', 'name', true, true, $search);

        $columns = new Columns(['name' => $requestColumn]);
        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testApplyNumericColumnSearchWithNumericValue(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->equalTo('e.id = :column_search_param_0'));

        $qb->expects($this->once())
            ->method('setParameter')
            ->with($this->equalTo('column_search_param_0'), $this->equalTo('123'));

        $column        = NumberColumn::new('id', 'ID')->setField('id');
        $search        = new Search('123', false);
        $requestColumn = new Column('id', 'id', true, true, $search);

        $columns = new Columns(['id' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipNumericColumnWithNonNumericValue(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $column        = NumberColumn::new('id', 'ID')->setField('id');
        $search        = new Search('abc', false);
        $requestColumn = new Column('id', 'id', true, true, $search);

        $columns = new Columns(['id' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipNonSearchableColumn(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $column        = TextColumn::new('name', 'Name')->setField('name')->setSearchable(false);
        $search        = new Search('test', false);
        $requestColumn = new Column('name', 'name', true, true, $search);

        $columns = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipEmptySearchValue(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $column        = TextColumn::new('name', 'Name')->setField('name');
        $search        = new Search('', false);
        $requestColumn = new Column('name', 'name', true, true, $search);

        $columns = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipNullSearchValue(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $column        = TextColumn::new('name', 'Name')->setField('name');
        $search        = new Search(null, false);
        $requestColumn = new Column('name', 'name', true, true, $search);

        $columns = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipWhitespaceOnlySearchValue(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $column        = TextColumn::new('name', 'Name')->setField('name');
        $search        = new Search('   ', false);
        $requestColumn = new Column('name', 'name', true, true, $search);

        $columns = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testSkipWhenColumnNotInRequest(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->never())
            ->method('andWhere');

        $qb->expects($this->never())
            ->method('setParameter');

        $column = TextColumn::new('name', 'Name')->setField('name');

        $columns = new Columns([]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testMultipleColumnSearchesWithAndLogic(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);

        $qb->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function ($condition) {
                static $callCount = 0;
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('e.name LIKE :column_search_param_0', $condition);
                } elseif (2 === $callCount) {
                    $this->assertEquals('e.email LIKE :column_search_param_1', $condition);
                }

                return $this->createMock(QueryBuilder::class);
            });

        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function ($param, $value) {
                static $callCount = 0;
                ++$callCount;
                if (1 === $callCount) {
                    $this->assertEquals('column_search_param_0', $param);
                    $this->assertEquals('%alice%', $value);
                } elseif (2 === $callCount) {
                    $this->assertEquals('column_search_param_1', $param);
                    $this->assertEquals('%example.com%', $value);
                }

                return $this->createMock(QueryBuilder::class);
            });

        $nameColumn  = TextColumn::new('name', 'Name')->setField('name');
        $emailColumn = TextColumn::new('email', 'Email')->setField('email');

        $nameSearch  = new Search('alice', false);
        $emailSearch = new Search('example.com', false);

        $nameRequestColumn  = new Column('name', 'name', true, true, $nameSearch);
        $emailRequestColumn = new Column('email', 'email', true, true, $emailSearch);

        $columns = new Columns([
            'name'  => $nameRequestColumn,
            'email' => $emailRequestColumn,
        ]);

        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$nameColumn, $emailColumn], 'e');

        $filter->apply($qb, $context);
    }
}
