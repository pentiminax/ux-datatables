<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchPredicateBuilderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\Filter\GlobalSearchFilter;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(GlobalSearchFilter::class)]
final class GlobalSearchFilterTest extends TestCase
{
    use BuildsQueryFilterContext;

    private function filter(): GlobalSearchFilter
    {
        return new GlobalSearchFilter(new DefaultSearchPredicateBuilder());
    }

    #[Test]
    public function it_can_be_constructed_with_no_arguments(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $qb->method('expr')->willReturn(new Expr());
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%ali%');

        $column  = TextColumn::new('name', 'Name')->setField('name');
        $context = $this->globalSearchContext($column, 'ali');

        (new GlobalSearchFilter())->apply($qb, $context);
    }

    /**
     * @return iterable<string, array{ColumnInterface, string, string, string, ?array{string, string}}>
     */
    public static function appliedGlobalSearches(): iterable
    {
        yield 'simple field' => [
            TextColumn::new('name', 'Name')->setField('name'),
            'test',
            'UX_DATATABLES_SEARCH(e.name, :search_param_0) = 1',
            '%test%',
            null,
        ];

        yield 'field with like wildcards is escaped, not interpreted' => [
            TextColumn::new('name', 'Name')->setField('name'),
            '50%_off',
            'UX_DATATABLES_SEARCH(e.name, :search_param_0) = 1',
            '%50!%!_off%',
            null,
        ];

        yield 'dot notation field' => [
            TextColumn::new('authorName', 'Author')->setField('author.firstName'),
            'john',
            'UX_DATATABLES_SEARCH(author.firstName, :search_param_0) = 1',
            '%john%',
            ['e.author', 'author'],
        ];
    }

    /**
     * @param ?array{string, string} $expectedJoin
     */
    #[Test]
    #[DataProvider('appliedGlobalSearches')]
    public function it_applies_global_search(
        ColumnInterface $column,
        string $value,
        string $expectedCondition,
        string $expectedParameterValue,
        ?array $expectedJoin,
    ): void {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        if (null === $expectedJoin) {
            $qb->expects($this->never())->method('leftJoin');
        } else {
            $qb->expects($this->once())
                ->method('leftJoin')
                ->with($expectedJoin[0], $expectedJoin[1])
                ->willReturn($qb);
        }

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with($expectedCondition)
            ->willReturn(new Expr\Orx([$expectedCondition]));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', $expectedParameterValue);

        $this->filter()->apply($qb, $this->globalSearchContext($column, $value));
    }

    #[Test]
    public function it_builds_conditions_through_the_injected_predicate_builder(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('e.name = :search_param_0')
            ->willReturn(new Expr\Orx(['e.name = :search_param_0']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', 'exact');

        $predicateBuilder = new class implements SearchPredicateBuilderInterface {
            public function build(QueryBuilder $qb, ColumnInterface $column, string $alias, string $field, string $value, string $paramName, bool $forceNumeric = false): ?string
            {
                $qb->setParameter($paramName, $value);

                return \sprintf('%s.%s = :%s', $alias, $field, $paramName);
            }
        };

        $filter = new GlobalSearchFilter($predicateBuilder);
        $column = TextColumn::new('name', 'Name')->setField('name');

        $filter->apply($qb, $this->globalSearchContext($column, 'exact'));
    }

    #[Test]
    public function it_skips_text_column_when_field_requires_an_explicit_scalar_path(): void
    {
        $qb = $this->associationFieldQueryBuilder('client');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('leftJoin');

        $context = $this->globalSearchContext(TextColumn::new('client', 'Client'), 'acme');

        $this->filter()->apply($qb, $context);
    }

    private function globalSearchContext(ColumnInterface $column, string $value): QueryFilterContext
    {
        $request = new DataTableRequest(1, new Columns([]), search: new Search($value, false));

        return $this->context($request, [$column]);
    }

    #[Test]
    public function it_uses_search_field_instead_of_field_when_set(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.donorProvider', 'donorProvider')
            ->willReturn($qb);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('UX_DATATABLES_SEARCH(donorProvider.name, :search_param_0) = 1')
            ->willReturn(new Expr\Orx(['UX_DATATABLES_SEARCH(donorProvider.name, :search_param_0) = 1']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%acme%');

        $column  = TextColumn::new('donorProviderName', 'Donor')->setSearchField('donorProvider.name');
        $request = new DataTableRequest(1, new Columns([]), search: new Search('acme', false));

        $this->filter()->apply($qb, $this->context($request, [$column]));
    }

    #[Test]
    public function it_uses_custom_predicate_when_set_and_returns_non_null(): void
    {
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

        $request = new DataTableRequest(1, new Columns([]), search: new Search('acme', false));
        $this->filter()->apply($qb, $this->context($request, [$column]));

        $this->assertSame(['search_param_0_n' => '%acme%', 'search_param_0_l' => '%acme%'], $paramsCaptured);
    }

    #[Test]
    public function it_falls_back_to_field_when_custom_predicate_returns_null(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $expr = $this->createMock(Expr::class);
        $expr->expects($this->once())
            ->method('orX')
            ->with('UX_DATATABLES_SEARCH(e.name, :search_param_0) = 1')
            ->willReturn(new Expr\Orx(['UX_DATATABLES_SEARCH(e.name, :search_param_0) = 1']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%test%');

        $column = TextColumn::new('name', 'Name')
            ->setField('name')
            ->setSearchPredicate(static fn () => null);

        $request = new DataTableRequest(1, new Columns([]), search: new Search('test', false));
        $this->filter()->apply($qb, $this->context($request, [$column]));
    }

    #[Test]
    public function it_applies_search_joins_before_building_predicate(): void
    {
        $addedJoin = $this->createMock(Join::class);
        $addedJoin->method('getAlias')->willReturn('dp');
        $addedJoin->method('getJoin')->willReturn('e.donorProvider');

        $joinParts = [];
        $qb        = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturnCallback(function () use (&$joinParts) {
            return $joinParts;
        });

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
            ->willReturn(new Expr\Orx(['UX_DATATABLES_SEARCH(dp.name, :search_param_0) = 1']));

        $qb->method('expr')->willReturn($expr);
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('search_param_0', '%acme%');

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $request = new DataTableRequest(1, new Columns([]), search: new Search('acme', false));
        $this->filter()->apply($qb, $this->context($request, [$column]));
    }

    #[Test]
    public function it_skips_search_join_when_alias_already_registered(): void
    {
        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('dp');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn(['e' => [$existingJoin]]);

        $qb->expects($this->never())->method('leftJoin');

        $expr = $this->createMock(Expr::class);
        $expr->method('orX')->willReturn(new Expr\Orx([]));
        $qb->method('expr')->willReturn($expr);

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $request = new DataTableRequest(1, new Columns([]), search: new Search('acme', false));
        $this->filter()->apply($qb, $this->context($request, [$column]));
    }
}
