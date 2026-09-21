<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\Pagination\PaginatorInterface;
use ApiPlatform\State\Pagination\PartialPaginatorInterface;
use ApiPlatform\State\ProviderInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformQueryParameterFactory;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Column as RequestColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * @internal
 */
#[CoversClass(ApiPlatformCollectionProvider::class)]
final class ApiPlatformCollectionProviderTest extends TestCase
{
    private const string ENTITY_CLASS = 'App\Entity\Book';

    #[Test]
    public function it_reads_a_whole_page_with_one_call_to_the_state_provider(): void
    {
        $items    = $this->items(200);
        $provider = $this->recordingProvider($this->paginator($items, 640));

        $result = $this->provider($provider)->fetchData($this->request(length: 200));

        $this->assertCount(1, $provider->calls);
        $this->assertCount(200, iterator_to_array($result->data));
    }

    #[Test]
    public function it_reports_the_total_items_the_paginator_carries(): void
    {
        $provider = $this->recordingProvider($this->paginator($this->items(50), 640));

        $result = $this->provider($provider)->fetchData($this->request(length: 50));

        $this->assertSame(640, $result->recordsTotal);
        $this->assertSame(640, $result->recordsFiltered);
    }

    #[Test]
    public function it_counts_the_rows_when_the_operation_returns_no_paginator(): void
    {
        $provider = $this->recordingProvider($this->items(3));

        $result = $this->provider($provider)->fetchData($this->request());

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(3, $result->recordsFiltered);
    }

    #[Test]
    public function it_passes_the_resolved_operation_and_a_request_to_the_state_provider(): void
    {
        $provider = $this->recordingProvider($this->paginator($this->items(1), 1));

        $this->provider($provider)->fetchData($this->request(start: 20, length: 10));

        [$operation, $uriVariables, $context] = $provider->calls[0];

        $this->assertInstanceOf(GetCollection::class, $operation);
        $this->assertSame([], $uriVariables);
        $this->assertSame(self::ENTITY_CLASS, $context['resource_class']);
        $this->assertSame($operation, $context['operation']);
        $this->assertInstanceOf(Request::class, $context['request']);
        $this->assertSame('/api/books', $context['request']->getPathInfo());
        $this->assertSame(['page' => '3', 'itemsPerPage' => '10'], $context['request']->query->all());
        $this->assertSame($operation, $context['request']->attributes->get('_api_operation'));
        $this->assertSame(self::ENTITY_CLASS, $context['request']->attributes->get('_api_resource_class'));
    }

    #[Test]
    public function it_throws_when_the_entity_exposes_no_collection_operation(): void
    {
        $provider = $this->recordingProvider([]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('No API Platform collection operation was found for "App\Entity\Book".');

        $this->provider($provider, resource: new ApiResource())->fetchData($this->request());
    }

    #[Test]
    public function it_walks_the_collection_page_by_page_on_export(): void
    {
        $provider = new RecordingProvider([
            $this->paginator($this->items(2), 5, lastPage: 3),
            $this->paginator($this->items(2), 5, lastPage: 3),
            $this->paginator($this->items(1), 5, lastPage: 3),
        ]);

        $rows = iterator_to_array(
            $this->provider($provider, exportChunkSize: 2)->iterateRows($this->request()->withoutPagination()),
            false,
        );

        $this->assertCount(5, $rows);
        $this->assertSame(
            [['page' => '1', 'itemsPerPage' => '2'], ['page' => '2', 'itemsPerPage' => '2'], ['page' => '3', 'itemsPerPage' => '2']],
            array_map(static fn (array $call): array => $call[2]['request']->query->all(), $provider->calls),
        );
    }

    #[Test]
    public function it_stops_exporting_on_the_last_page_the_operation_reports(): void
    {
        $provider = new RecordingProvider([$this->paginator($this->items(1), 1)]);

        iterator_to_array(
            $this->provider($provider, exportChunkSize: 250)->iterateRows($this->request()->withoutPagination()),
            false,
        );

        $this->assertCount(1, $provider->calls);
    }

    #[Test]
    public function it_exports_every_page_when_the_operation_caps_the_page_size(): void
    {
        // An operation declaring paginationMaximumItemsPerPage returns pages shorter than the ones
        // asked for. Reading the end of the collection off the requested chunk size would truncate
        // the export at the first page, silently.
        $provider = new RecordingProvider([
            $this->paginator($this->items(2), 5, lastPage: 3),
            $this->paginator($this->items(2), 5, lastPage: 3),
            $this->paginator($this->items(1), 5, lastPage: 3),
        ]);

        $rows = iterator_to_array(
            $this->provider($provider, exportChunkSize: 250)->iterateRows($this->request()->withoutPagination()),
            false,
        );

        $this->assertCount(5, $rows);
        $this->assertCount(3, $provider->calls);
    }

    #[Test]
    public function it_exports_in_one_pass_when_the_operation_does_not_paginate(): void
    {
        // Pagination disabled on the operation: everything arrives at once, and asking for a second
        // page would return the same rows forever.
        $provider = new RecordingProvider([$this->items(250), $this->items(250)]);

        $rows = iterator_to_array(
            $this->provider($provider, exportChunkSize: 250)->iterateRows($this->request()->withoutPagination()),
            false,
        );

        $this->assertCount(250, $rows);
        $this->assertCount(1, $provider->calls);
    }

    #[Test]
    public function it_follows_a_partial_paginator_until_it_returns_a_short_page(): void
    {
        $provider = new RecordingProvider([
            $this->partialPaginator($this->items(2)),
            $this->partialPaginator($this->items(1), itemsPerPage: 2),
        ]);

        $rows = iterator_to_array(
            $this->provider($provider, exportChunkSize: 2)->iterateRows($this->request()->withoutPagination()),
            false,
        );

        $this->assertCount(3, $rows);
        $this->assertCount(2, $provider->calls);
    }

    #[Test]
    public function it_reads_the_requested_window_when_the_offset_is_not_a_multiple_of_the_page_size(): void
    {
        // Scroller scrolls to an arbitrary row: start=37 with length=50 lands inside page 1, whose
        // rows 37..49 only cover part of the window. The remainder comes from page 2.
        $provider = new RecordingProvider([
            $this->paginator($this->items(50), 640, lastPage: 13),
            $this->paginator($this->items(50, 50), 640, lastPage: 13),
        ]);

        $result = $this->provider($provider)->fetchData($this->request(start: 37, length: 50));
        $rows   = iterator_to_array($result->data, false);

        $this->assertCount(50, $rows);
        $this->assertSame('Book 37', $rows[0]['title']);
        $this->assertSame('Book 86', $rows[49]['title']);
        $this->assertSame(
            [['page' => '1', 'itemsPerPage' => '50'], ['page' => '2', 'itemsPerPage' => '50']],
            array_map(static fn (array $call): array => $call[2]['request']->query->all(), $provider->calls),
        );
    }

    #[Test]
    public function it_serves_a_misaligned_offset_from_the_last_page_alone(): void
    {
        $provider = new RecordingProvider([$this->paginator($this->items(50), 50, lastPage: 1)]);

        $result = $this->provider($provider)->fetchData($this->request(start: 37, length: 50));
        $rows   = iterator_to_array($result->data, false);

        $this->assertCount(13, $rows);
        $this->assertSame('Book 37', $rows[0]['title']);
        $this->assertCount(1, $provider->calls);
    }

    #[Test]
    public function it_reads_an_aligned_offset_with_a_single_call(): void
    {
        $provider = $this->recordingProvider($this->paginator($this->items(50), 640, lastPage: 13));

        $result = $this->provider($provider)->fetchData($this->request(start: 100, length: 50));

        $this->assertCount(50, iterator_to_array($result->data));
        $this->assertCount(1, $provider->calls);
        $this->assertSame(['page' => '3', 'itemsPerPage' => '50'], $provider->calls[0][2]['request']->query->all());
    }

    #[Test]
    public function it_fills_the_window_when_the_operation_caps_the_page_size(): void
    {
        // The operation serves 20 rows whatever itemsPerPage asks for -- a cap, or API Platform's
        // default of ignoring the client's page size. The page number the request carries was
        // computed from the requested length, so it points at the wrong rows: both it and the
        // offset are recomputed from the size the paginator reports.
        $provider = new RecordingProvider([
            $this->paginator($this->items(20, 40), 640, lastPage: 32),
            $this->paginator($this->items(20, 120), 640, lastPage: 32),
            $this->paginator($this->items(20, 140), 640, lastPage: 32),
            $this->paginator($this->items(20, 160), 640, lastPage: 32),
            $this->paginator($this->items(20, 180), 640, lastPage: 32),
        ]);

        $result = $this->provider($provider)->fetchData($this->request(start: 137, length: 50));
        $rows   = iterator_to_array($result->data, false);

        $this->assertCount(50, $rows);
        $this->assertSame('Book 137', $rows[0]['title']);
        $this->assertSame('Book 186', $rows[49]['title']);

        // Page 3 is what the requested length pointed at; rows 137..186 live on pages 7 to 10 of
        // the 20-row pages the operation actually serves.
        $this->assertSame(
            ['3', '7', '8', '9', '10'],
            array_map(static fn (array $call): string => $call[2]['request']->query->get('page'), $provider->calls),
        );
    }

    #[Test]
    public function it_stops_filling_the_window_at_the_last_page(): void
    {
        $provider = new RecordingProvider([
            $this->paginator($this->items(20), 50, lastPage: 3),
            $this->paginator($this->items(20, 20), 50, lastPage: 3),
            $this->paginator($this->items(10, 40), 50, lastPage: 3),
        ]);

        $result = $this->provider($provider)->fetchData($this->request(start: 37, length: 50));
        $rows   = iterator_to_array($result->data, false);

        $this->assertCount(13, $rows);
        $this->assertSame('Book 37', $rows[0]['title']);
        $this->assertSame('Book 49', $rows[12]['title']);
        $this->assertCount(3, $provider->calls);
    }

    #[Test]
    public function it_refuses_a_column_control_criterion(): void
    {
        $provider = $this->recordingProvider($this->paginator($this->items(1), 1));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('ColumnControl searches are not supported on an API Platform collection.');

        $this->provider($provider)->fetchData($this->request(columnControl: new ColumnControl(
            search: new ColumnControlSearch(value: 'dune', logic: ColumnControlLogic::Contains, type: 'text'),
        )));
    }

    private function provider(
        RecordingProvider $stateProvider,
        ?ApiResource $resource = null,
        int $exportChunkSize = 250,
    ): ApiPlatformCollectionProvider {
        $resource ??= (new ApiResource())->withOperations(new Operations([
            new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
        ]));

        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory
            ->method('create')
            ->willReturn(new ResourceMetadataCollection(self::ENTITY_CLASS, [$resource]));

        $rowMapper = new class implements RowMapperInterface {
            public function map(mixed $row): array
            {
                return ['title' => $row->title];
            }
        };

        return new ApiPlatformCollectionProvider(
            stateProvider: $stateProvider,
            collectionResolver: new ApiResourceCollectionUrlResolver($metadataFactory),
            queryParameterFactory: new ApiPlatformQueryParameterFactory(),
            intentFactory: new DefaultDataTableQueryIntentFactory(),
            columnResolver: new ColumnResolver(),
            requestStack: new RequestStack(),
            entityClass: self::ENTITY_CLASS,
            columns: [TextColumn::new('title', 'Title')->setField('title')],
            rowMapper: $rowMapper,
            exportChunkSize: $exportChunkSize,
        );
    }

    private function recordingProvider(mixed $data): RecordingProvider
    {
        return new RecordingProvider([$data]);
    }

    /**
     * @return list<object>
     */
    private function items(int $count, int $from = 0): array
    {
        $items = [];

        for ($index = 0; $index < $count; ++$index) {
            $number  = $from + $index;
            $items[] = (object) ['title' => "Book $number"];
        }

        return $items;
    }

    /**
     * @param list<object> $items
     */
    private function paginator(array $items, int $totalItems, int $lastPage = 1): PaginatorInterface
    {
        return new class($items, $totalItems, $lastPage) implements PaginatorInterface, \IteratorAggregate {
            /**
             * @param list<object> $items
             */
            public function __construct(
                private readonly array $items,
                private readonly int $totalItems,
                private readonly int $lastPage,
            ) {
            }

            public function getIterator(): \Traversable
            {
                return new \ArrayIterator($this->items);
            }

            public function count(): int
            {
                return \count($this->items);
            }

            public function getCurrentPage(): float
            {
                return 1.0;
            }

            public function getItemsPerPage(): float
            {
                return (float) \count($this->items);
            }

            public function getLastPage(): float
            {
                return (float) $this->lastPage;
            }

            public function getTotalItems(): float
            {
                return (float) $this->totalItems;
            }
        };
    }

    /**
     * @param list<object> $items
     */
    private function partialPaginator(array $items, ?int $itemsPerPage = null): PartialPaginatorInterface
    {
        return new class($items, $itemsPerPage ?? \count($items)) implements PartialPaginatorInterface, \IteratorAggregate {
            /**
             * @param list<object> $items
             */
            public function __construct(
                private readonly array $items,
                private readonly int $itemsPerPage,
            ) {
            }

            public function getIterator(): \Traversable
            {
                return new \ArrayIterator($this->items);
            }

            public function count(): int
            {
                return \count($this->items);
            }

            public function getCurrentPage(): float
            {
                return 1.0;
            }

            public function getItemsPerPage(): float
            {
                return (float) $this->itemsPerPage;
            }
        };
    }

    private function request(int $start = 0, int $length = 10, ?ColumnControl $columnControl = null): DataTableRequest
    {
        return new DataTableRequest(
            draw: 1,
            columns: new Columns([
                'title' => new RequestColumn(
                    data: 'title',
                    name: 'title',
                    searchable: true,
                    orderable: true,
                    columnControl: $columnControl,
                ),
            ]),
            start: $start,
            length: $length,
            search: new Search(value: null, regex: false),
        );
    }
}

/**
 * @internal
 */
final class RecordingProvider implements ProviderInterface
{
    /** @var list<array{0: Operation, 1: array<string, mixed>, 2: array<string, mixed>}> */
    public array $calls = [];

    private int $index = 0;

    /**
     * @param list<mixed> $responses
     */
    public function __construct(private readonly array $responses)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $this->calls[] = [$operation, $uriVariables, $context];

        return $this->responses[$this->index++] ?? [];
    }
}
