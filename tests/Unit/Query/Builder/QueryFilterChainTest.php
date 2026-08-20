<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Builder;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\QueryFilterInterface;
use Pentiminax\UX\DataTables\Contracts\SearchStrategyInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterChain;
use Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Query\Strategy\SearchStrategyRegistry;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(QueryFilterChain::class)]
final class QueryFilterChainTest extends TestCase
{
    use BuildsQueryFilterContext;

    #[Test]
    public function it_applies_added_filters_in_order(): void
    {
        $calls = [];
        $qb    = $this->createMock(QueryBuilder::class);

        $chain = (new QueryFilterChain())
            ->addFilter($this->recordingFilter('first', $calls))
            ->addFilter($this->recordingFilter('second', $calls))
            ->addFilter($this->recordingFilter('third', $calls));

        $context = $this->context(new DataTableRequest(1, new Columns([])), []);
        $result  = $chain->apply($qb, $context);

        $this->assertSame($qb, $result);
        $this->assertSame(['first', 'second', 'third'], $calls);
    }

    #[Test]
    public function it_builds_the_default_chain_wired_with_the_given_registry(): void
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

        $context = $this->context(new DataTableRequest(1, $requestColumns), [
            TextColumn::new('name', 'Name')->setField('name'),
            TextColumn::new('email', 'Email')->setField('email'),
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        QueryFilterChain::createDefault($registry, new DefaultSearchPredicateBuilder())->apply($qb, $context);

        // Both ColumnSearchFilter (always 'contains') and ColumnControlSearchFilter (logic
        // 'contains' picked for the 'email' column) resolve the strategy from the same
        // injected registry, proving createDefault() threads it through to both.
        $this->assertSame([
            ['name', 'ali'],
            ['email', 'bob'],
        ], $calls);
    }

    #[Test]
    public function it_builds_the_default_chain_with_only_a_registry_argument(): void
    {
        $requestColumns = new Columns([
            'name' => new Column('name', 'name', true, true, new Search('ali', false)),
        ]);

        $context = $this->context(new DataTableRequest(1, $requestColumns), [
            TextColumn::new('name', 'Name')->setField('name'),
        ]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $qb->expects($this->atLeastOnce())
            ->method('andWhere')
            ->with('e.name LIKE :column_control_param_0');

        // predicateBuilder defaults to DefaultSearchPredicateBuilder() -- callers who only
        // ever passed a registry before GlobalSearchFilter took a second constructor
        // argument must keep working unchanged.
        QueryFilterChain::createDefault(new DefaultSearchStrategyRegistry())->apply($qb, $context);
    }

    /**
     * Regression test: ColumnSearchFilter and ColumnControlSearchFilter both resolve their
     * strategy from the same registry and, for a while, both let the strategy mint a
     * column_control_param_N name from a shared, column-position-based index. A column with
     * both an ordinary search box value and a scalar ColumnControl search reused the exact
     * same parameter name for both conditions; ColumnControlSearchFilter runs after
     * ColumnSearchFilter, so its setParameter() call silently overwrote the ordinary search's
     * bound value, leaving both andWhere() conditions evaluated against the ColumnControl term.
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

        $context = $this->context(new DataTableRequest(1, $requestColumns), [
            TextColumn::new('name', 'Name')->setField('name'),
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

        QueryFilterChain::createDefault(new DefaultSearchStrategyRegistry())->apply($qb, $context);

        $this->assertCount(
            2,
            $parameters,
            'ordinary search and column control search collapsed onto the same parameter name: '
                .implode(', ', array_keys($parameters))
        );
        // The ordinary search box always goes through the 'contains' strategy (LIKE), while
        // the ColumnControl search used ColumnControlLogic::Equal (exact match) -- confirming
        // each parameter still carries the value and shape its own search form produced.
        $this->assertSame(['%ali%', 'Alice'], array_values($parameters));

        // Every parameter name the conditions reference must actually have been bound --
        // the bug this guards was never a missing bind, it was two DQL fragments sharing
        // one name while only the last setParameter() call for that name took effect.
        foreach (array_keys($parameters) as $paramName) {
            $this->assertStringContainsString(":{$paramName}", implode(' ', $conditions));
        }
    }

    #[Test]
    public function it_resets_param_index_for_stability_between_filters(): void
    {
        $context = $this->context(new DataTableRequest(1, new Columns([])), [
            TextColumn::new('name', 'Name')->setField('name'),
        ]);

        $indices          = [];
        $paramIndexFilter = function () use (&$indices): QueryFilterInterface {
            return new class($indices) implements QueryFilterInterface {
                public function __construct(private array &$indices)
                {
                }

                public function apply(QueryBuilder $qb, QueryFilterContext $context): void
                {
                    $column = $context->intent->columns[0];

                    // Same reference requested twice within this one filter -- stable, as a
                    // filter building one bound parameter referenced by two DQL fragments
                    // needs. Recorded to distinguish from the cross-filter case below.
                    $this->indices[] = [$context->paramIndexFor($column), $context->paramIndexFor($column)];
                }
            };
        };

        $chain = (new QueryFilterChain())
            ->addFilter($paramIndexFilter())
            ->addFilter($paramIndexFilter());

        $chain->apply($this->createMock(QueryBuilder::class), $context);

        // Each filter is internally stable (both entries in a pair match), but the two
        // filters never share a value with each other -- QueryFilterChain::apply() resets
        // the scope between them, so the second filter draws fresh indices even though it
        // asked about the exact same column reference the first filter already used.
        $this->assertSame([0, 0], $indices[0]);
        $this->assertSame([1, 1], $indices[1]);
    }

    /**
     * @param list<string> $calls
     */
    private function recordingFilter(string $label, array &$calls): QueryFilterInterface
    {
        return new class($label, $calls) implements QueryFilterInterface {
            public function __construct(
                private readonly string $label,
                private array &$calls,
            ) {
            }

            public function apply(QueryBuilder $qb, QueryFilterContext $context): void
            {
                $this->calls[] = $this->label;
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
