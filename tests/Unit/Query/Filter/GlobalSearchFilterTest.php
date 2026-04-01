<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Filter\GlobalSearchFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GlobalSearchFilter::class)]
final class GlobalSearchFilterTest extends TestCase
{
    #[Test]
    public function it_applies_with_dot_notation_field(): void
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

    #[Test]
    public function it_skips_text_column_when_field_is_a_doctrine_association(): void
    {
        $filter = new GlobalSearchFilter();

        $metadata = $this->createMock(ClassMetadata::class);
        $metadata->method('hasAssociation')->with('client')->willReturn(true);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getClassMetadata')->willReturn($metadata);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);
        $qb->method('getRootEntities')->willReturn(['App\\Entity\\Project']);
        $qb->method('getEntityManager')->willReturn($em);

        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('leftJoin');

        $column  = TextColumn::new('client', 'Client');
        $columns = new Columns([]);
        $search  = new Search('acme', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = new QueryFilterContext($request, [$column], 'e');

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_applies_with_simple_field(): void
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
