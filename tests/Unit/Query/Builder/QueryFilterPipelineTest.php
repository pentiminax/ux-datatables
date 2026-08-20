<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Builder;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\FilterInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
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
}
