<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Profiler\DataTableProfiler;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableResponseTest extends TestCase
{
    /**
     * @param array<string, mixed>|null $query
     * @param array<string, mixed>      $expected
     */
    #[Test]
    #[DataProvider('provideResponses')]
    public function it_builds_the_json_response(?array $query, ?DataProviderInterface $provider, array $expected): void
    {
        $table = new ResponseTestTable($provider);

        if (null !== $query) {
            $table->handleRequest(new Request(query: $query));
        }

        $this->assertSame($expected, json_decode((string) $table->getResponse()->getContent(), true));
    }

    /**
     * @return iterable<string, array{array<string, mixed>|null, DataProviderInterface|null, array<string, mixed>}>
     */
    public static function provideResponses(): iterable
    {
        yield 'no request handled' => [
            null,
            null,
            ['draw' => 1, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []],
        ];

        yield 'request handled without a provider' => [
            ['draw' => 7],
            null,
            ['draw' => 7, 'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => []],
        ];

        yield 'request handled with a provider' => [
            ['draw' => 3],
            new FixedResultDataProvider(),
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
    public function it_retries_prepare_for_rendering_after_a_failed_hydration(): void
    {
        $table = new RetryPreparationTestTable();

        try {
            $table->getDataTable();
            $this->fail('Expected first rendering preparation to fail.');
        } catch (\LogicException $exception) {
            $this->assertSame('First hydration failed.', $exception->getMessage());
        }

        $table->getDataTable();
        $table->getDataTable();

        $this->assertSame(2, $table->providerCalls);
    }

    /**
     * Regression test: profiler collection used to live only in AjaxDataController, so a
     * table using a custom ajax() URL -- calling handleRequest()/getResponse() directly, the
     * documented "Manual Same-Route Handling" pattern, without ever going through that
     * controller -- never reached the profiler at all. Collection now lives inside
     * getResponse() itself, so this path is captured the same as the built-in route.
     */
    #[Test]
    public function it_records_the_ajax_query_with_the_profiler_regardless_of_which_controller_calls_it(): void
    {
        $profiler = new DataTableProfiler();
        $table    = new ResponseTestTable(new FixedResultDataProvider());
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault(profiler: $profiler));

        $table->handleRequest(new Request(query: ['draw' => 3]));
        $table->getResponse();

        $queries = $profiler->getAjaxQueries();

        $this->assertCount(1, $queries);
        $this->assertSame(ResponseTestTable::class, $queries[0]['class']);
        $this->assertSame(10, $queries[0]['recordsTotal']);
        $this->assertSame(4, $queries[0]['recordsFiltered']);
        $this->assertSame(2, $queries[0]['rowCount']);
    }

    #[Test]
    public function it_builds_the_response_without_a_profiler_wired(): void
    {
        $table = new ResponseTestTable(new FixedResultDataProvider());
        $table->handleRequest(new Request(query: ['draw' => 3]));

        $response = $table->getResponse();

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function it_throws_when_client_side_auto_provider_cannot_be_created(): void
    {
        $table = new MissingEntityManagerHydrationTestTable();

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('EntityManagerInterface is required to auto-configure a DoctrineDataProvider');

        $table->getDataTable();
    }
}

final class ResponseTestTable extends AbstractDataTable
{
    public function __construct(private readonly ?DataProviderInterface $provider = null)
    {
        parent::__construct();
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        return $this->provider;
    }
}

final class FixedResultDataProvider implements DataProviderInterface
{
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

final class RetryPreparationTestTable extends AbstractDataTable
{
    public int $providerCalls = 0;

    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }

    protected function createDataProvider(): ?DataProviderInterface
    {
        ++$this->providerCalls;

        if (1 === $this->providerCalls) {
            throw new \LogicException('First hydration failed.');
        }

        return new ArrayDataProvider([], $this->createRowMapper());
    }
}

#[AsDataTable(entityClass: \stdClass::class)]
final class MissingEntityManagerHydrationTestTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id');
    }
}
