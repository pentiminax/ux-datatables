<?php

declare(strict_types=1);

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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnSearchFilter::class)]
final class ColumnSearchFilterTest extends TestCase
{
    #[Test]
    public function it_applies_text_column_search_with_dot_notation(): void
    {
        $filter = new ColumnSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.author', 'author')
            ->willReturn($qb);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($this->equalTo('author.firstName LIKE :column_search_param_0'));

        $qb->expects($this->once())
            ->method('setParameter')
            ->with($this->equalTo('column_search_param_0'), $this->equalTo('%john%'));

        $column        = TextColumn::new('authorName', 'Author')->setField('author.firstName');
        $search        = new Search('john', false);
        $requestColumn = new Column('authorName', 'authorName', true, true, $search);

        $columns = new Columns(['authorName' => $requestColumn]);
        $request = new DataTableRequest(1, $columns);

        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_applies_text_column_search(): void
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

    #[Test]
    public function it_applies_numeric_column_search_with_numeric_value(): void
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

    #[Test]
    public function it_skips_numeric_column_with_non_numeric_value(): void
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

    #[Test]
    public function it_skips_non_searchable_column(): void
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

    #[Test]
    public function it_skips_empty_search_value(): void
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

    #[Test]
    public function it_skips_null_search_value(): void
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

    #[Test]
    public function it_skips_whitespace_only_search_value(): void
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

    #[Test]
    public function it_skips_when_column_not_in_request(): void
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

    #[Test]
    public function it_applies_multiple_column_searches_with_and_logic(): void
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
