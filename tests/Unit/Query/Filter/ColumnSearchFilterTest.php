<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Query\Filter\ColumnSearchFilter;
use Pentiminax\UX\DataTables\Query\Strategy\ContainsSearchStrategy;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ColumnSearchFilter::class)]
final class ColumnSearchFilterTest extends TestCase
{
    use BuildsQueryFilterContext;

    private function filter(): ColumnSearchFilter
    {
        return new ColumnSearchFilter(new SearchStrategyRegistry([new ContainsSearchStrategy()]));
    }

    #[Test]
    public function it_can_be_constructed_with_no_arguments(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('UX_DATATABLES_SEARCH(e.name, :column_control_param_0) = 1');

        $context = $this->singleColumnContext(TextColumn::new('name', 'Name')->setField('name'), new Search('ali', false));

        (new ColumnSearchFilter())->apply($qb, $context);
    }

    /**
     * @return iterable<string, array{ColumnInterface, string, string, string, ?array{string, string}}>
     */
    public static function appliedColumnSearches(): iterable
    {
        yield 'text field' => [
            TextColumn::new('name', 'Name')->setField('name'),
            'test',
            'UX_DATATABLES_SEARCH(e.name, :column_control_param_0) = 1',
            '%test%',
            null,
        ];

        yield 'text field with like wildcards is escaped, not interpreted' => [
            TextColumn::new('name', 'Name')->setField('name'),
            '50%_off',
            'UX_DATATABLES_SEARCH(e.name, :column_control_param_0) = 1',
            '%50!%!_off%',
            null,
        ];

        yield 'numeric field with a numeric value' => [
            NumberColumn::new('id', 'ID')->setField('id'),
            '123',
            'e.id = :column_control_param_0',
            '123',
            null,
        ];

        yield 'dot notation field' => [
            TextColumn::new('authorName', 'Author')->setField('author.firstName'),
            'john',
            'UX_DATATABLES_SEARCH(author.firstName, :column_control_param_0) = 1',
            '%john%',
            ['e.author', 'author'],
        ];
    }

    /**
     * @return iterable<string, array{ColumnInterface, ?Search, bool}>
     */
    public static function skippedColumnSearches(): iterable
    {
        yield 'numeric field with a non numeric value' => [
            NumberColumn::new('id', 'ID')->setField('id'),
            new Search('abc', false),
            true,
        ];

        yield 'non searchable column' => [
            TextColumn::new('name', 'Name')->setField('name')->setSearchable(false),
            new Search('test', false),
            true,
        ];

        yield 'empty value' => [
            TextColumn::new('name', 'Name')->setField('name'),
            new Search('', false),
            true,
        ];

        yield 'null value' => [
            TextColumn::new('name', 'Name')->setField('name'),
            new Search(null, false),
            true,
        ];

        yield 'whitespace only value' => [
            TextColumn::new('name', 'Name')->setField('name'),
            new Search('   ', false),
            true,
        ];

        yield 'column missing from the request' => [
            TextColumn::new('name', 'Name')->setField('name'),
            null,
            false,
        ];
    }

    /**
     * @param ?array{string, string} $expectedJoin
     */
    #[Test]
    #[DataProvider('appliedColumnSearches')]
    public function it_applies_column_search(
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

        $qb->expects($this->once())
            ->method('andWhere')
            ->with($expectedCondition);

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_0', $expectedParameterValue);

        $context = $this->singleColumnContext($column, new Search($value, false));

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    #[DataProvider('skippedColumnSearches')]
    public function it_skips_column_search(ColumnInterface $column, ?Search $search, bool $inRequest): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');

        $context = $this->singleColumnContext($column, $search, inRequest: $inRequest);

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_skips_text_column_when_field_requires_an_explicit_scalar_path(): void
    {
        $qb = $this->associationFieldQueryBuilder('client');
        $qb->expects($this->never())->method('andWhere');
        $qb->expects($this->never())->method('setParameter');
        $qb->expects($this->never())->method('leftJoin');

        $context = $this->singleColumnContext(TextColumn::new('client', 'Client'), new Search('acme', false));

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_applies_column_search_when_the_request_prepends_a_client_only_column(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('UX_DATATABLES_SEARCH(e.name, :column_control_param_0) = 1');

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_0', '%alice%');

        $requestColumns = new Columns([
            ''     => new Column('', '', false, false),
            'name' => new Column('name', 'name', true, true, new Search('alice', false)),
        ]);

        $context = $this->context(new DataTableRequest(1, $requestColumns), [
            TextColumn::new('name', 'Name')->setField('name'),
        ]);

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_applies_multiple_column_searches_with_and_logic(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $conditions = [];
        $parameters = [];

        $qb->expects($this->exactly(2))
            ->method('andWhere')
            ->willReturnCallback(function (string $condition) use (&$conditions, $qb): QueryBuilder {
                $conditions[] = $condition;

                return $qb;
            });

        $qb->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(function (string $name, mixed $value) use (&$parameters, $qb): QueryBuilder {
                $parameters[$name] = $value;

                return $qb;
            });

        $requestColumns = new Columns([
            'name'  => new Column('name', 'name', true, true, new Search('alice', false)),
            'email' => new Column('email', 'email', true, true, new Search('example.com', false)),
        ]);

        $context = $this->context(new DataTableRequest(1, $requestColumns), [
            TextColumn::new('name', 'Name')->setField('name'),
            TextColumn::new('email', 'Email')->setField('email'),
        ]);

        $this->filter()->apply($qb, $context);

        self::assertSame([
            'UX_DATATABLES_SEARCH(e.name, :column_control_param_0) = 1',
            'UX_DATATABLES_SEARCH(e.email, :column_control_param_1) = 1',
        ], $conditions);

        self::assertSame([
            'column_control_param_0' => '%alice%',
            'column_control_param_1' => '%example.com%',
        ], $parameters);
    }

    #[Test]
    public function it_uses_search_field_instead_of_field_for_column_search(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('leftJoin')
            ->with('e.donorProvider', 'donorProvider')
            ->willReturn($qb);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('UX_DATATABLES_SEARCH(donorProvider.name, :column_control_param_0) = 1');

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_0', '%acme%');

        $column        = TextColumn::new('donorProviderName', 'Donor')->setSearchField('donorProvider.name');
        $search        = new Search('acme', false);
        $requestColumn = new Column('donorProviderName', 'donorProviderName', true, true, $search);
        $context       = $this->context(new DataTableRequest(1, new Columns(['donorProviderName' => $requestColumn])), [$column]);

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_uses_custom_predicate_and_and_wheres_the_result(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('dp.name LIKE :column_control_param_0');

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_0', '%acme%');

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->setSearchPredicate(
                function (QueryBuilder $qb, string $alias, string $value, string $paramName): string {
                    $qb->setParameter($paramName, '%'.$value.'%');

                    return "dp.name LIKE :{$paramName}";
                }
            );

        $search        = new Search('acme', false);
        $requestColumn = new Column('donorProviderName', 'donorProviderName', true, true, $search);
        $context       = $this->context(new DataTableRequest(1, new Columns(['donorProviderName' => $requestColumn])), [$column]);

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_falls_back_to_field_when_custom_predicate_returns_null(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn([]);

        $qb->expects($this->once())
            ->method('andWhere')
            ->with('UX_DATATABLES_SEARCH(e.name, :column_control_param_0) = 1');

        $qb->expects($this->once())
            ->method('setParameter')
            ->with('column_control_param_0', '%test%');

        $column = TextColumn::new('name', 'Name')
            ->setField('name')
            ->setSearchPredicate(static fn () => null);

        $search        = new Search('test', false);
        $requestColumn = new Column('name', 'name', true, true, $search);
        $context       = $this->context(new DataTableRequest(1, new Columns(['name' => $requestColumn])), [$column]);

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_applies_search_joins_before_column_search_predicate(): void
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

        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())->method('setParameter');

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $search        = new Search('acme', false);
        $requestColumn = new Column('donorProviderName', 'donorProviderName', true, true, $search);
        $context       = $this->context(new DataTableRequest(1, new Columns(['donorProviderName' => $requestColumn])), [$column]);

        $this->filter()->apply($qb, $context);
    }

    #[Test]
    public function it_skips_search_join_when_alias_already_registered(): void
    {
        $existingJoin = $this->createMock(Join::class);
        $existingJoin->method('getAlias')->willReturn('dp');

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->with('join')->willReturn(['e' => [$existingJoin]]);

        $qb->expects($this->never())->method('leftJoin');
        $qb->expects($this->once())->method('andWhere')->willReturn($qb);
        $qb->expects($this->once())->method('setParameter');

        $column = TextColumn::new('donorProviderName', 'Donor')
            ->addSearchJoin('e.donorProvider', 'dp')
            ->setSearchField('dp.name');

        $search        = new Search('acme', false);
        $requestColumn = new Column('donorProviderName', 'donorProviderName', true, true, $search);
        $context       = $this->context(new DataTableRequest(1, new Columns(['donorProviderName' => $requestColumn])), [$column]);

        $this->filter()->apply($qb, $context);
    }
}
