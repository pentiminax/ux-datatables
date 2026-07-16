<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Builder;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\FilterInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryFilterPipeline::class)]
final class QueryFilterPipelineTest extends TestCase
{
    #[Test]
    public function it_applies_a_configured_filter_with_the_submitted_value_and_root_alias(): void
    {
        $qb     = $this->createMock(QueryBuilder::class);
        $filter = $this->recordingFilter('status');

        $filters = (new Filters())->add($filter);

        $request = $this->request(filters: ['status' => 'active']);

        $result = $this->pipeline()->apply(
            qb: $qb,
            request: $request,
            columns: [TextColumn::new('name', 'Name')->setField('name')],
            filters: $filters,
            registry: new DefaultSearchStrategyRegistry(),
        );

        $this->assertSame($qb, $result);
        $this->assertSame([[$qb, 'active', 'e']], $filter->applied);
    }

    #[Test]
    public function it_skips_configured_filters_with_empty_values(): void
    {
        $qb     = $this->createMock(QueryBuilder::class);
        $filter = $this->recordingFilter('status');

        $filters = (new Filters())->add($filter);

        $request = $this->request(filters: ['status' => '']);

        $this->pipeline()->apply(
            qb: $qb,
            request: $request,
            columns: [TextColumn::new('name', 'Name')->setField('name')],
            filters: $filters,
            registry: new DefaultSearchStrategyRegistry(),
        );

        $this->assertSame([], $filter->applied);
    }

    #[Test]
    public function it_normalizes_name_keyed_columns_to_a_list(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        // Name-keyed columns (as produced after permission filtering) must not
        // break the intent factory, which requires a positional list.
        $columns = ['name' => TextColumn::new('name', 'Name')->setField('name')];

        $result = $this->pipeline()->apply(
            qb: $qb,
            request: $this->request(),
            columns: $columns,
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
        );

        $this->assertSame($qb, $result);
    }

    #[Test]
    public function it_is_a_no_op_on_configured_filters_when_none_are_declared(): void
    {
        $qb = $this->createMock(QueryBuilder::class);

        $result = $this->pipeline()->apply(
            qb: $qb,
            request: $this->request(),
            columns: [TextColumn::new('name', 'Name')->setField('name')],
            filters: null,
            registry: new DefaultSearchStrategyRegistry(),
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
