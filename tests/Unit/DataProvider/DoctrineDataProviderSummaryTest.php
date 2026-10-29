<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\DoctrineDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Model\Summary\Average;
use Pentiminax\UX\DataTables\Model\Summary\Count;
use Pentiminax\UX\DataTables\Model\Summary\Max;
use Pentiminax\UX\DataTables\Model\Summary\Min;
use Pentiminax\UX\DataTables\Model\Summary\Sum;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Strategy\DefaultSearchStrategyRegistry;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\CountLine;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryNote;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryOrder;
use Pentiminax\UX\DataTables\Tests\Fixtures\Count\SummaryRegion;
use Pentiminax\UX\DataTables\Tests\Support\BuildsEntityManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Summaries cover every row matching the search, filters and scope, never just the page.
 *
 * @internal
 */
#[CoversClass(DoctrineDataProvider::class)]
final class DoctrineDataProviderSummaryTest extends TestCase
{
    use BuildsEntityManager;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->em = $this->createEntityManager(SummaryOrder::class, SummaryNote::class, SummaryRegion::class);

        $north = new SummaryRegion(1, 'North', 10);
        $south = new SummaryRegion(2, 'South', 20);

        $orders = [
            new SummaryOrder(1, 'paid', 1000, $north),
            new SummaryOrder(2, 'paid', 2000, $north),
            new SummaryOrder(3, 'pending', 500, $south),
            new SummaryOrder(4, 'paid', null, $south),
            new SummaryOrder(5, 'pending', 700),
        ];

        foreach (['urgent', 'urgent soon', 'plain'] as $text) {
            $orders[0]->addNote($text);
        }
        foreach (['urgent', 'plain'] as $text) {
            $orders[1]->addNote($text);
        }
        $orders[2]->addNote('plain');
        foreach (['a', 'b', 'c', 'd'] as $text) {
            $orders[4]->addNote($text);
        }

        foreach ($orders as $order) {
            $this->em->persist($order);
        }

        $this->em->flush();
        $this->em->clear();
    }

    #[Test]
    public function it_aggregates_every_row_not_only_the_page(): void
    {
        $result = $this->fetch(request: $this->request(length: 2));

        $this->assertCount(2, iterator_to_array($result->data));
        $this->assertSame(['amount' => ['sum' => 4200, 'avg' => 1050.0, 'min' => 500, 'max' => 2000, 'count' => 4]], $result->summaries);
    }

    #[Test]
    public function it_reports_no_summaries_when_no_column_declares_one(): void
    {
        $result = $this->fetch(columns: [NumberColumn::new('amount')->setField('amount')]);

        $this->assertNull($result->summaries);
    }

    #[Test]
    public function it_follows_the_global_search(): void
    {
        $result = $this->fetch(request: $this->request(search: 'paid'));

        $this->assertSame(3000, $result->summaries['amount']['sum']);
        $this->assertSame(2, $result->summaries['amount']['count']);
        $this->assertSame(3, $result->recordsFiltered);
    }

    #[Test]
    public function it_follows_the_filters(): void
    {
        $filters = (new Filters())->add(ChoiceFilter::new('status')->options(['Pending' => 'pending']));

        $result = $this->fetch(request: $this->request(filters: ['status' => 'pending']), filters: $filters);

        $this->assertSame(1200, $result->summaries['amount']['sum']);
    }

    #[Test]
    public function it_respects_the_scope_of_customize_query_builder(): void
    {
        $result = $this->fetch(scope: static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->andWhere('e.status = :scope')
            ->setParameter('scope', 'pending'));

        $this->assertSame(1200, $result->summaries['amount']['sum']);
        $this->assertSame(2, $result->summaries['amount']['count']);
    }

    #[Test]
    public function a_to_many_join_does_not_inflate_sums_or_counts(): void
    {
        $result = $this->fetch(scope: static fn (QueryBuilder $qb): QueryBuilder => $qb->leftJoin('e.notes', 'n'));

        $this->assertSame(4200, $result->summaries['amount']['sum']);
        $this->assertSame(4, $result->summaries['amount']['count']);
    }

    #[Test]
    public function a_search_matching_several_children_counts_each_root_once(): void
    {
        $notes = TextColumn::new('notes')->setField('id')->setSearchField('notes.text');

        $result = $this->fetch(
            request: $this->request(search: 'urgent'),
            columns: [$notes, $this->amountColumn()],
        );

        $this->assertSame(3000, $result->summaries['amount']['sum']);
        $this->assertSame(2, $result->summaries['amount']['count']);
    }

    #[Test]
    public function it_summarizes_a_field_of_a_to_one_relation(): void
    {
        $quota = NumberColumn::new('quota')->setField('region.quota')->summarize(new Sum());

        $result = $this->fetch(columns: [$quota]);

        $this->assertSame(['quota' => ['sum' => 60]], $result->summaries);
    }

    #[Test]
    public function a_relation_already_joined_by_the_scope_does_not_clash_with_the_summary(): void
    {
        $quota = NumberColumn::new('quota')->setField('region.quota')->summarize(new Sum());

        $result = $this->fetch(
            columns: [$quota],
            scope: static fn (QueryBuilder $qb): QueryBuilder => $qb->innerJoin('e.region', 'region')->andWhere('region.name = :name')->setParameter('name', 'South'),
        );

        $this->assertSame(['quota' => ['sum' => 40]], $result->summaries);
    }

    #[Test]
    public function it_returns_null_aggregates_and_a_zero_count_when_nothing_matches(): void
    {
        $result = $this->fetch(request: $this->request(search: 'nothing-matches'));

        $this->assertSame(['sum' => null, 'avg' => null, 'min' => null, 'max' => null, 'count' => 0], $result->summaries['amount']);
    }

    #[Test]
    public function a_summarized_to_many_field_is_rejected(): void
    {
        $notes = TextColumn::new('notes')->setField('notes.text')->summarize(new Count());

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('crosses a to-many association');

        $this->fetch(columns: [$this->amountColumn(), $notes]);
    }

    #[Test]
    public function a_having_on_a_hidden_alias_still_aggregates_the_matching_roots(): void
    {
        $result = $this->fetch(scope: static fn (QueryBuilder $qb): QueryBuilder => $qb
            ->leftJoin('e.notes', 'n')
            ->addSelect('COUNT(n.id) AS HIDDEN noteCount')
            ->groupBy('e.id')
            ->having('noteCount > 0'));

        $this->assertSame(4200, $result->summaries['amount']['sum']);
        $this->assertSame(4, $result->summaries['amount']['count']);
    }

    #[Test]
    public function filter_scope_and_to_many_join_apply_together(): void
    {
        $filters = (new Filters())->add(ChoiceFilter::new('status')->options(['Paid' => 'paid']));

        $result = $this->fetch(
            request: $this->request(filters: ['status' => 'paid']),
            filters: $filters,
            scope: static fn (QueryBuilder $qb): QueryBuilder => $qb->leftJoin('e.notes', 'n')->andWhere('e.amount IS NOT NULL'),
        );

        $this->assertSame(3000, $result->summaries['amount']['sum']);
        $this->assertSame(2, $result->summaries['amount']['count']);
    }

    #[Test]
    public function a_composite_identifier_is_a_clear_error(): void
    {
        $em = $this->createEntityManager(CountLine::class);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('composite');

        (new DoctrineDataProvider(
            em: $em,
            entityClass: CountLine::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return [];
                }
            },
            columns: [NumberColumn::new('orderId')->setField('orderId')->summarize(new Sum())],
        ))->fetchData($this->request());
    }

    #[Test]
    public function an_unmapped_field_is_a_clear_configuration_error(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('The column "virtual" cannot be summarized on the server because "virtual" is not a mapped field');

        $this->fetch(columns: [NumberColumn::new('virtual')->setField('virtual')->summarize(new Sum())]);
    }

    /**
     * @param list<ColumnInterface> $columns
     */
    private function fetch(?DataTableRequest $request = null, ?array $columns = null, ?Filters $filters = null, ?callable $scope = null): \Pentiminax\UX\DataTables\Model\DataTableResult
    {
        $columns ??= [TextColumn::new('status')->setField('status'), $this->amountColumn()];
        $request ??= $this->request();

        $provider = new DoctrineDataProvider(
            em: $this->em,
            entityClass: SummaryOrder::class,
            rowMapper: new class implements RowMapperInterface {
                public function map(mixed $row): array
                {
                    return ['id' => $row->id];
                }
            },
            configureQueryBuilder: static function (QueryBuilder $qb, DataTableRequest $request) use ($columns, $filters, $scope): QueryBuilder {
                if (null !== $scope) {
                    $scope($qb);
                }

                return (new QueryFilterPipeline(new DefaultDataTableQueryIntentFactory()))->apply($qb, $request, $columns, $filters, new DefaultSearchStrategyRegistry());
            },
            columns: $columns,
        );

        return $provider->fetchData($request);
    }

    private function amountColumn(): NumberColumn
    {
        return NumberColumn::new('amount')->setField('amount')->summarize(new Sum(), new Average(), new Min(), new Max(), new Count());
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function request(int $length = 10, ?string $search = null, array $filters = []): DataTableRequest
    {
        return new DataTableRequest(
            draw: 1,
            columns: new Columns([
                'status' => new Column('status', 'status', true, true),
                'amount' => new Column('amount', 'amount', true, true),
            ]),
            length: $length,
            search: null === $search ? null : new Search($search, false),
            filters: $filters,
        );
    }
}
