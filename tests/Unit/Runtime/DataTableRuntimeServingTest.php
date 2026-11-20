<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Tests\Support\BuildsRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DataTableRuntime::class)]
final class DataTableRuntimeServingTest extends TestCase
{
    use BuildsRuntime;

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideResponses')]
    public function it_builds_the_json_response(?DataProviderInterface $provider, array $query, array $expected): void
    {
        $call = self::buildRuntime()->handle(new ServingTable($provider), new Request(query: $query));

        $this->assertSame($expected, json_decode((string) $call->response()->getContent(), true));
    }

    /**
     * @return iterable<string, array{DataProviderInterface|null, array<string, mixed>, array<string, mixed>}>
     */
    public static function provideResponses(): iterable
    {
        yield 'without a provider' => [
            null,
            ['draw' => 7],
            ['draw' => 7, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []],
        ];

        yield 'with a provider' => [
            new ServingFixedResultProvider(),
            ['draw' => 3],
            [
                'draw'            => 3,
                'recordsTotal'    => 10,
                'recordsFiltered' => 4,
                'data'            => [
                    ['id' => 1, 'name' => 'Alien'],
                    ['id' => 2, 'name' => 'Heat'],
                ],
            ],
        ];
    }

    #[Test]
    public function it_answers_the_empty_response_when_no_request_is_bound(): void
    {
        $response = self::buildRuntime()->open(new ServingTable())->response();

        $this->assertSame(
            ['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []],
            json_decode((string) $response->getContent(), true),
        );
    }

    #[Test]
    public function it_does_not_answer_a_request_without_a_draw(): void
    {
        $runtime = self::buildRuntime();

        $this->assertNull($runtime->respond(new ServingTable(new ServingFixedResultProvider()), new Request()));
        $this->assertFalse($runtime->handle(new ServingTable(), new Request())->isHandled());
    }

    #[Test]
    public function it_responds_to_a_data_tables_request(): void
    {
        $response = self::buildRuntime()->respond(new ServingTable(new ServingFixedResultProvider()), new Request(query: ['draw' => 3]));

        $this->assertSame(200, $response?->getStatusCode());
        $this->assertSame(3, json_decode((string) $response->getContent(), true)['draw']);
    }

    #[Test]
    public function it_resolves_the_provider_and_fetches_data_through_the_call(): void
    {
        $call = self::buildRuntime()->handle(new ServingTable(new ServingFixedResultProvider()), new Request(query: ['draw' => 2]));

        $this->assertNotNull($call->dataProvider());
        $this->assertSame(10, $call->fetchData(new DataTableRequest(draw: 1, columns: new Columns([])))->recordsTotal);
    }

    #[Test]
    public function it_records_the_ajax_query_with_the_profiler_whichever_controller_responds(): void
    {
        $profiler = new DataTableProfiler();
        $runtime  = self::buildRuntime(profiler: $profiler);

        $runtime->respond(new ServingTable(new ServingFixedResultProvider()), new Request(query: ['draw' => 3]));

        $queries = $profiler->getAjaxQueries();

        $this->assertCount(1, $queries);
        $this->assertSame(ServingTable::class, $queries[0]['class']);
        $this->assertSame(10, $queries[0]['recordsTotal']);
        $this->assertSame(4, $queries[0]['recordsFiltered']);
        $this->assertSame(2, $queries[0]['rowCount']);
    }

    /**
     * A page load carries no DataTables payload: with a profiler wired it must neither resolve a
     * data provider (this table has no EntityManager to build one from) nor record a query.
     */
    #[Test]
    public function it_ignores_a_request_without_a_draw_even_with_a_profiler_wired(): void
    {
        $profiler = new DataTableProfiler();

        $response = self::buildRuntime(profiler: $profiler)->respond(new ServingMissingEntityManagerTable(), new Request());

        $this->assertNull($response);
        $this->assertSame([], $profiler->getAjaxQueries());
    }

    #[Test]
    public function it_adds_search_list_options_to_the_ajax_response(): void
    {
        $response = self::buildRuntime()->respond(
            new ServingSearchListTable(new ServingFixedResultProvider()),
            new Request(query: ['draw' => 3]),
        );

        $payload = json_decode((string) $response?->getContent(), true);

        $this->assertSame([
            'status' => [['label' => 'Draft', 'value' => 'draft']],
        ], $payload['columnControl']);
    }

    #[Test]
    public function it_retries_a_render_after_a_failed_hydration(): void
    {
        $runtime = self::buildRuntime();
        $table   = new ServingRetryTable();

        try {
            $runtime->render($table);
            $this->fail('Expected the first render to fail.');
        } catch (\LogicException $exception) {
            $this->assertSame('First hydration failed.', $exception->getMessage());
        }

        $runtime->render($table);

        $this->assertGreaterThan(1, \count($table->providerCalls));
    }

    #[Test]
    public function it_throws_when_the_client_side_auto_provider_cannot_be_created(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EntityManagerInterface is required to auto-configure a DoctrineDataProvider');

        self::buildRuntime()->render(new ServingMissingEntityManagerTable());
    }

    #[Test]
    public function it_binds_the_handled_http_request_to_the_hooks_only(): void
    {
        $runtime = self::buildRuntime();
        $table   = new ServingHttpRequestTable();
        $request = new Request(query: ['draw' => 3, 'genre' => 'sci-fi']);

        $this->assertNull($table->exposedHttpRequest());
        $this->assertNull($runtime->open($table)->hooks->table->exposedHttpRequest());
        $this->assertSame($request, $runtime->handle($table, $request)->hooks->table->exposedHttpRequest());
        $this->assertNull($table->exposedHttpRequest());
    }

    #[Test]
    public function it_reads_the_current_http_request_from_the_query_builder_hook(): void
    {
        $call = self::buildRuntime()->handle(new ServingHttpRequestTable(), new Request(query: ['draw' => 3, 'genre' => 'sci-fi']));

        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->once())
            ->method('andWhere')
            ->with('e.genre = :genre')
            ->willReturn($qb);
        $qb->expects($this->once())
            ->method('setParameter')
            ->with('genre', 'sci-fi')
            ->willReturn($qb);

        $this->assertSame($qb, $call->hooks->customizeQueryBuilder($qb, new DataTableRequest(draw: 3, columns: new Columns([]))));
    }

    #[Test]
    public function it_gives_hooks_the_current_request(): void
    {
        $call = self::buildRuntime()->handle(new ServingCurrentRequestTable(), new Request(query: ['draw' => 7]));

        $call->response();

        $this->assertSame(7, $call->hooks->table->seenDraw);
    }
}

final class ServingTable extends AbstractDataTable
{
    public function __construct(private readonly ?DataProviderInterface $provider = null)
    {
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        return $this->provider;
    }
}

final class ServingFixedResultProvider implements DataProviderInterface
{
    public function supports(DataSourceFeature $feature): bool
    {
        return true;
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return $this->fetchData($request)->data;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(
            recordsTotal: 10,
            recordsFiltered: 4,
            data: [
                ['id' => 1, 'name' => 'Alien'],
                ['id' => 2, 'name' => 'Heat'],
            ],
        );
    }
}

final class ServingSearchListTable extends AbstractDataTable
{
    public function __construct(private readonly DataProviderInterface $provider)
    {
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('status')->columnControl([
            [
                'target'  => 1,
                'content' => [SearchList::new()->ajaxOptionsProvider(
                    static fn (): array => [['label' => 'Draft', 'value' => 'draft']],
                )],
            ],
        ]);
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->columnControl();
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        return $this->provider;
    }
}

final class ServingRetryTable extends AbstractDataTable
{
    /**
     * Shared by reference: the runtime binds a copy of the table to each render.
     */
    public \ArrayObject $providerCalls;

    public function __construct()
    {
        $this->providerCalls = new \ArrayObject();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        $this->providerCalls[] = true;

        if (1 === \count($this->providerCalls)) {
            throw new \LogicException('First hydration failed.');
        }

        return new ArrayDataProvider([], $this->createRowMapper());
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class ServingMissingEntityManagerTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(false);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}

final class ServingHttpRequestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function exposedHttpRequest(): ?Request
    {
        return $this->getHttpRequest();
    }

    public function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        $genre = $this->getHttpRequest()?->query->get('genre');
        if (null === $genre) {
            return $qb;
        }

        return $qb
            ->andWhere('e.genre = :genre')
            ->setParameter('genre', $genre);
    }
}

final class ServingCurrentRequestTable extends AbstractDataTable
{
    public ?int $seenDraw = null;

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    public function createDataProvider(): ?DataProviderInterface
    {
        return new ServingCallbackProvider(fn (): array => $this->mapRow(['id' => 1]));
    }

    public function mapRow(mixed $row): array
    {
        $this->seenDraw = $this->getCurrentRequest()?->draw;

        return $row;
    }
}

final class ServingCallbackProvider implements DataProviderInterface
{
    public function __construct(private readonly \Closure $callback)
    {
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return true;
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return $this->fetchData($request)->data;
    }

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        return new DataTableResult(recordsTotal: 1, recordsFiltered: 1, data: [($this->callback)()]);
    }
}
