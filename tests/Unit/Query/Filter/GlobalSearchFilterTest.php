<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Filter\GlobalSearchFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use PHPUnit\Framework\TestCase;

class GlobalSearchFilterTest extends TestCase
{
    public function testApplyWithDotNotationField(): void
    {
        $filter = new GlobalSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.author', 'author')
            ->willReturn($qb);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('author.firstName LIKE :search_param_0')
            ->willReturn(new Expr\Orx(['author.firstName LIKE :search_param_0']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%john%');

        $column  = TextColumn::new('authorName', 'Author')->setField('author.firstName');
        $columns = new Columns([]);
        $search  = new Search('john', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    public function testApplyWithSimpleField(): void
    {
        $filter = new GlobalSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('e.name LIKE :search_param_0')
            ->willReturn(new Expr\Orx(['e.name LIKE :search_param_0']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%test%');

        $qb->expects($this->never())->method('leftJoin');

        $column  = TextColumn::new('name', 'Name')->setField('name');
        $columns = new Columns([]);
        $search  = new Search('test', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }
}
