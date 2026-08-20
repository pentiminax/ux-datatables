<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Filter\GlobalSearchFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GlobalSearchFilter::class)]
final class GlobalSearchFilterTest extends TestCase
{
    use BuildsQueryFilterContext;

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
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_skips_text_column_when_field_requires_an_explicit_scalar_path(): void
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
        $context = $this->context($request, [$column]);

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
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);
    }

    // -----------------------------------------------------------------------
    // setSearchField override
    // -----------------------------------------------------------------------

    #[Test]
    public function it_uses_search_field_instead_of_field_when_set(): void
    {
        $filter = new GlobalSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.donorProvider', 'donorProvider')
            ->willReturn($qb);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('donorProvider.name LIKE :search_param_0')
            ->willReturn(new Expr\Orx(['donorProvider.name LIKE :search_param_0']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%acme%');

        // setSearchField() is the search-only override; setField() is left at default.
        $column  = TextColumn::new('donorProviderName', 'Donor')->setSearchField('donorProvider.name');
        $columns = new Columns([]);
        $search  = new Search('acme', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);
    }

    // -----------------------------------------------------------------------
    // setSearchPredicate / SearchableColumnInterface
    // -----------------------------------------------------------------------

    #[Test]
    public function it_uses_custom_predicate_when_set_and_returns_non_null(): void
    {
        $filter = new GlobalSearchFilter();

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('dp.name LIKE :search_param_0_n OR dp.legalName LIKE :search_param_0_l')
            ->willReturn(new Expr\Orx(['dp.name LIKE :search_param_0_n OR dp.legalName LIKE :search_param_0_l']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);

        $paramsCaptured = [];
        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use ($qb, &$paramsCaptured) {
                $paramsCaptured[$name] = $value;

                return $qb;
            });

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->setSearchPredicate(
                function (QueryBuilder $qb, string $alias, string $value, string $paramName): string {
                    $qb->setParameter($paramName.'_n', '%'.$value.'%');
                    $qb->setParameter($paramName.'_l', '%'.$value.'%');

                    return "dp.name LIKE :{$paramName}_n OR dp.legalName LIKE :{$paramName}_l";
                }
            );

        $columns = new Columns([]);
        $search  = new Search('acme', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);

        $this->assertSame(['search_param_0_n' => '%acme%', 'search_param_0_l' => '%acme%'], $paramsCaptured);
    }

    #[Test]
    public function it_falls_back_to_field_when_custom_predicate_returns_null(): void
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

        // Predicate returns null → standard LIKE predicate on the resolved field.
        $column = TextColumn::new('name', 'Name')
            ->setField('name')
            ->setSearchPredicate(static fn () => null);

        $columns = new Columns([]);
        $search  = new Search('test', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);
    }

    // -----------------------------------------------------------------------
    // addSearchJoin
    // -----------------------------------------------------------------------

    #[Test]
    public function it_applies_search_joins_before_building_predicate(): void
    {
        $filter = new GlobalSearchFilter();

        // Track joins so RelationFieldResolver sees 'dp' after applySearchJoins runs.
        $addedJoin = $this->createMock(Join::class);
        $addedJoin->method('getAlias')->willReturn('dp');
        $addedJoin->method('getJoin')->willReturn('e.donorProvider');

        $joinParts = [];
        $qb        = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturnCallback(function () use (&$joinParts) {
            return $joinParts;
        });

        // The explicit join declared on the column must be applied first.
        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.donorProvider', 'dp')
            ->willReturnCallback(function () use ($qb, $addedJoin, &$joinParts) {
                $joinParts['e'][] = $addedJoin;

                return $qb;
            });

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->willReturn(new Expr\Orx(['dp.name LIKE :search_param_0']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%acme%');

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $columns = new Columns([]);
        $search  = new Search('acme', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);
    }

    #[Test]
    public function it_skips_search_join_when_alias_already_registered(): void
    {
        $filter = new GlobalSearchFilter();

        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('dp');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn(['e' => [$existingJoin]]);

        // Join already present — must NOT be added again.
        $qb->expects($this->never())->method('leftJoin');

        $expr = $this->createMock(Expr::class);
        $expr->method('orX')->willReturn(new Expr\Orx([]));
        $qb->method('expr')->willReturn($expr);

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $columns = new Columns([]);
        $search  = new Search('acme', false);
        $request = new DataTableRequest(1, $columns, search: $search);
        $context = $this->context($request, [$column]);

        $filter->apply($qb, $context);
    }
}
