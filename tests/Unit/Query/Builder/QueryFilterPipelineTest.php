<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Builder;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\FilterInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryFilterPipeline::class)]
final class QueryFilterPipelineTest extends TestCase
{
    /**
     * @return iterable<string, array{array<string, ColumnInterface>}>
     */
    public static function columnShapes(): iterable
    {
        yield 'positional list' => [[TextColumn::new('name', 'Name')->setField('name')]];

        // Name-keyed columns (as produced after permission filtering) must not
        // break the intent factory, which requires a positional list.
        yield 'name keyed' => [['name' => TextColumn::new('name', 'Name')->setField('name')]];
    }

    /**
     * @param list<string> $expectedValues
     */
    #[Test]
    #[TestWith(['active', ['active']])]
    #[TestWith(['', []])]
    public function it_applies_configured_filters_only_for_non_empty_values(string $value, array $expectedValues): void
    {
        $qb     = $this->createMock(QueryBuilder::class);
        $filter = $this->recordingFilter('status');

        $result = $this->pipeline()->apply(
            qb: $qb,
            request: $this->request(filters: ['status' => $value]),
            columns: [TextColumn::new('name', 'Name')->setField('name')],
            filters: (new Filters())->add($filter),
            registry: new DefaultSearchStrategyRegistry(),
            predicateBuilder: new DefaultSearchPredicateBuilder(),
        );

        $expected = array_map(static fn (string $applied): array => [$qb, $applied, 'e'], $expectedValues);

        $this->assertSame($qb, $result);
        $this->assertSame($expected, $filter->applied);
    }

    /**
     * @param array<string, ColumnInterface> $columns
     */
    #[Test]
    #[DataProvider('columnShapes')]
    public function it_is_a_no_op_on_configured_filters_when_none_are_declared(array $columns): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $result = $this->pipeline()->apply(
            qb: $qb,
            request: $this->request(),
            columns: $columns,
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
            predicateBuilder: new DefaultSearchPredicateBuilder(),
        );

        $this->assertSame($qb, $result);
    }

    #[Test]
    public function it_applies_without_a_predicate_builder_argument(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        // predicateBuilder defaults to DefaultSearchPredicateBuilder() -- callers who only
        // ever passed a registry before this parameter was added must keep working unchanged.
        $result = $this->pipeline()->apply(
            qb: $qb,
            request: $this->request(),
            columns: [TextColumn::new('name', 'Name')->setField('name')],
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
        );

        $this->assertSame($qb, $result);
    }

    #[Test]
    public function it_applies_column_search_and_column_control_through_the_given_registry(): void
    {
        $calls    = [];
        $strategy = $this->recordingContainsStrategy($calls);
        $registry = new SearchStrategyRegistry([$strategy]);

        $requestColumns = new Columns([
            'name'  => new Column('name', 'name', true, true, new Search('ali', false)),
            'email' => new Column(
                'email',
                'email',
                true,
                true,
                columnControl: new ColumnControl(search: new ColumnControlSearch('bob', ColumnControlLogic::Contains, 'text')),
            ),
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $this->pipeline()->apply(
            qb: $qb,
            request: new DataTableRequest(1, $requestColumns),
            columns: [
                TextColumn::new('name', 'Name')->setField('name'),
                TextColumn::new('email', 'Email')->setField('email'),
            ],
            filters: null,
            registry: $registry,
            predicateBuilder: new DefaultSearchPredicateBuilder(),
        );

        $this->assertSame([
            ['name', 'ali'],
            ['email', 'bob'],
        ], $calls);
    }

    /**
     * Regression test: ColumnSearchFilter and ColumnControlSearchFilter both resolve their
     * strategy from the same registry and, for a while, both let the strategy mint a
     * column_control_param_N name from a shared, column-position-based index. A column with
     * both an ordinary search box value and a scalar ColumnControl search reused the exact
     * same parameter name for both conditions; ColumnControlSearchFilter runs after
     * ColumnSearchFilter, so its setParameter() call silently overwrote the ordinary search's
     * bound value, leaving both andWhere() conditions evaluated against the ColumnControl term.
     *
     * QueryFilterPipeline resets paramIndexFor() stability between those filters so two
     * filters on the same column still receive distinct Doctrine parameter names.
     */
    #[Test]
    public function it_keeps_ordinary_and_column_control_search_parameters_distinct_for_the_same_column(): void
    {
        $requestColumns = new Columns([
            'name' => new Column(
                'name',
                'name',
                true,
                true,
                search: new Search('ali', false),
                columnControl: new ColumnControl(search: new ColumnControlSearch('Alice', ColumnControlLogic::Equal, 'text')),
            ),
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $conditions = [];
        $qb->method('andWhere')->willReturnCallback(function (string $condition) use ($qb, &$conditions): QueryBuilder {
            $conditions[] = $condition;

            return $qb;
        });

        $parameters = [];
        $qb->method('setParameter')->willReturnCallback(
            function (string $name, mixed $value) use ($qb, &$parameters): QueryBuilder {
                $parameters[$name] = $value;

                return $qb;
            }
        );

        $this->pipeline()->apply(
            qb: $qb,
            request: new DataTableRequest(1, $requestColumns),
            columns: [TextColumn::new('name', 'Name')->setField('name')],
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
        );

        $this->assertCount(
            2,
            $parameters,
            'ordinary search and column control search collapsed onto the same parameter name: '
                .implode(', ', array_keys($parameters))
        );
        $this->assertSame(['%ali%', 'Alice'], array_values($parameters));

        foreach (array_keys($parameters) as $paramName) {
            $this->assertStringContainsString(":{$paramName}", implode(' ', $conditions));
        }
    }

    private function pipeline(): QueryFilterPipeline
    {
        return new QueryFilterPipeline(new DefaultDataTableQueryIntentFactory());
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function request(array $filters = []): DataTableRequest
    {
        $columns = new Columns(['name' => new Column('name', 'name', true, true)]);

        return new DataTableRequest(draw: 1, columns: $columns, filters: $filters);
    }

    private function recordingFilter(string $name): FilterInterface
    {
        return new class($name) implements FilterInterface {
            /** @var list<array{QueryBuilder, mixed, string}> */
            public array $applied = [];

            public function __construct(private readonly string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function apply(QueryBuilder $qb, mixed $value, string $alias): void
            {
                $this->applied[] = [$qb, $value, $alias];
            }

            public function jsonSerialize(): array
            {
                return ['name' => $this->name];
            }
        };
    }

    /**
     * @param list<array{string, string}> $calls
     */
    private function recordingContainsStrategy(array &$calls): SearchStrategyInterface
    {
        return new class($calls) implements SearchStrategyInterface {
            public function __construct(
                private array &$calls,
            ) {
            }

            public function apply(QueryBuilder $qb, ColumnInterface $column, ColumnControlSearch $search, int $paramIndex, string $alias): void
            {
                $this->calls[] = [$column->getName(), $search->value];
            }

            public function getLogic(): string
            {
                return 'contains';
            }
        };
    }
}
