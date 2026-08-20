<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\Query\Expr;
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

    /**
     * @return iterable<string, array{ColumnInterface, string, string, string, ?array{string, string}}>
     */
    public static function appliedGlobalSearches(): iterable
    {
        yield 'simple field' => [
            TextColumn::new('name', 'Name')->setField('name'),
            'test',
            "e.name LIKE :search_param_0 ESCAPE '!'",
            '%test%',
            null,
        ];

        yield 'field with like wildcards is escaped, not interpreted' => [
            TextColumn::new('name', 'Name')->setField('name'),
            '50%_off',
            "e.name LIKE :search_param_0 ESCAPE '!'",
            '%50!%!_off%',
            null,
        ];

        yield 'dot notation field' => [
            TextColumn::new('authorName', 'Author')->setField('author.firstName'),
            'john',
            "author.firstName LIKE :search_param_0 ESCAPE '!'",
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
}
