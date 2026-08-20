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
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
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

        QueryFilterChain::createDefault($registry)->apply($qb, $context);

        // Both ColumnSearchFilter (always 'contains') and ColumnControlSearchFilter (logic
        // 'contains' picked for the 'email' column) resolve the strategy from the same
        // injected registry, proving createDefault() threads it through to both.
        $this->assertSame([
            ['name', 'ali'],
            ['email', 'bob'],
        ], $calls);
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
