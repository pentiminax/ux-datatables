<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use Pentiminax\UX\DataTables\Runtime\SearchListOptionsResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DataTableRuntime::class)]
final class DataTableRuntimeTest extends TestCase
{
    #[Test]
    public function it_exposes_the_handled_http_request(): void
    {
        $runtime = $this->createRuntime();
        $request = new Request(query: ['draw' => 7, 'genre' => 'sci-fi']);

        $this->assertNull($runtime->getHttpRequest());

        $runtime->handleRequest($request);

        $this->assertSame($request, $runtime->getHttpRequest());
    }

    #[Test]
    #[DataProvider('responseCases')]
    public function it_builds_the_json_response(?Request $request, ?DataProviderInterface $provider, array $expected): void
    {
        $runtime = $this->createRuntime($provider);

        if (null !== $request) {
            $runtime->handleRequest($request);
        }

        $response = $runtime->getResponse();

        $this->assertSame($expected, json_decode((string) $response->getContent(), true));
    }

    /**
     * @return iterable<string, array{0: ?Request, 1: ?DataProviderInterface, 2: array<string, mixed>}>
     */
    public static function responseCases(): iterable
    {
        yield 'no request handled' => [
            null,
            null,
            [
                'draw'            => 1,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ],
        ];

        yield 'request handled without a provider' => [
            new Request(query: ['draw' => 7]),
            null,
            [
                'draw'            => 7,
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
            ],
        ];

        yield 'request handled with a provider result' => [
            new Request(query: ['draw' => 3]),
            new class implements DataProviderInterface {
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
            },
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
    public function it_caches_the_resolved_provider(): void
    {
        $provider     = $this->createStub(DataProviderInterface::class);
        $factoryCalls = 0;
        $runtime      = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static function () use ($provider, &$factoryCalls): ?DataProviderInterface {
                ++$factoryCalls;

                return $provider;
            },
        );

        $this->assertSame($provider, $runtime->getDataProvider());
        $this->assertSame($provider, $runtime->getDataProvider());
        $this->assertSame(1, $factoryCalls);
    }

    #[Test]
    public function it_adds_dynamic_search_list_options_to_the_json_response(): void
    {
        $column = TextColumn::new('status');
        $table  = (new DataTable('orders'))
            ->columns([$column])
            ->addExtension((new ColumnControlExtension([]))->add(1, [
                SearchList::new()->ajaxOptionsProvider(
                    static fn (DataTableRequest $request, ColumnInterface $resolvedColumn): array => [
                        ['label' => 'Draft', 'value' => 'draft'],
                    ],
                ),
            ]));
        $runtime = new DataTableRuntime(
            table: $table,
            dataProviderFactory: static fn (): DataProviderInterface => new class implements DataProviderInterface {
                public function fetchData(DataTableRequest $request): DataTableResult
                {
                    return new DataTableResult(1, 1, [['status' => 'draft']]);
                }
            },
            columns: [$column],
            searchListOptionsResolver: new SearchListOptionsResolver(),
        );
        $runtime->handleRequest(new Request(query: ['draw' => 7]));

        $this->assertSame([
            'draw'            => 7,
            'recordsTotal'    => 1,
            'recordsFiltered' => 1,
            'data'            => [['status' => 'draft']],
            'columnControl'   => [
                'status' => [['label' => 'Draft', 'value' => 'draft']],
            ],
        ], json_decode((string) $runtime->getResponse()->getContent(), true));
    }

    #[Test]
    #[DataProvider('showAllLengthMenus')]
    public function it_bounds_the_request_length_from_the_table_length_menu(array $lengthMenu): void
    {
        $table = (new DataTable('movies'))->lengthMenu($lengthMenu);

        $runtime = new DataTableRuntime(
            table: $table,
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
            maxPageLength: 100,
        );

        $runtime->handleRequest(new Request(query: ['draw' => 1, 'start' => -5, 'length' => -1]));

        $this->assertSame(-1, $runtime->getRequest()?->length);
        $this->assertSame(0, $runtime->getRequest()?->start);
    }

    /**
     * @return iterable<string, array{0: array<mixed>}>
     */
    public static function showAllLengthMenus(): iterable
    {
        yield 'flat menu' => [[10, 25, -1]];
        yield 'menu with its own labels' => [[[10, 25, -1], ['10', '25', 'All']]];
        yield 'menu with an object entry' => [[10, 25, ['label' => 'All', 'value' => -1]]];
        yield 'menu starting with an object entry' => [[
            ['label' => 'All', 'value' => -1],
            ['label' => '25', 'value' => 25],
        ]];
    }

    #[Test]
    #[DataProvider('invalidShowAllLengthMenus')]
    public function it_caps_show_all_for_malformed_object_entries(array $lengthMenu): void
    {
        $runtime = new DataTableRuntime(
            table: (new DataTable('movies'))->lengthMenu($lengthMenu),
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
            maxPageLength: 100,
        );

        $runtime->handleRequest(new Request(query: ['draw' => 1, 'length' => -1]));

        $this->assertSame(100, $runtime->getRequest()?->length);
    }

    /**
     * @return iterable<string, array{0: array<mixed>}>
     */
    public static function invalidShowAllLengthMenus(): iterable
    {
        yield 'missing value' => [[10, ['label' => 'All']]];
        yield 'string value' => [[10, ['label' => 'All', 'value' => '-1']]];
        yield 'different value' => [[10, ['label' => 'All', 'value' => -2]]];
    }

    #[Test]
    #[DataProvider('cappedLengths')]
    public function it_caps_length_when_the_length_menu_has_no_show_all(int $length, int $expected): void
    {
        $table = (new DataTable('movies'))->lengthMenu([10, 25]);

        $runtime = new DataTableRuntime(
            table: $table,
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
            maxPageLength: 100,
        );

        $runtime->handleRequest(new Request(query: ['draw' => 1, 'length' => $length]));

        $this->assertSame($expected, $runtime->getRequest()?->length);
    }

    #[Test]
    #[DataProvider('declaredPageLengths')]
    public function it_keeps_a_page_length_the_table_declares(DataTable $table, int $length, int $expected): void
    {
        $runtime = new DataTableRuntime(
            table: $table,
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
            maxPageLength: 100,
        );

        $runtime->handleRequest(new Request(query: ['draw' => 1, 'length' => $length]));

        $this->assertSame($expected, $runtime->getRequest()?->length);
    }

    /**
     * A declared page size is a developer decision, and the client paginates with it: capping it
     * below that size left the rows between the two lengths on no page at all.
     *
     * @return iterable<string, array{0: DataTable, 1: int, 2: int}>
     */
    public static function declaredPageLengths(): iterable
    {
        yield 'pageLength above the bound is served' => [
            (new DataTable('movies'))->pageLength(2000),
            2000,
            2000,
        ];

        yield 'a length menu entry above the bound is served' => [
            (new DataTable('movies'))->lengthMenu([500, 2000]),
            2000,
            2000,
        ];

        yield 'anything beyond the declared sizes is still capped' => [
            (new DataTable('movies'))->pageLength(2000),
            999999,
            2000,
        ];

        // A refused "show all" is not a page size the client paginates with, so it still falls
        // back to the configured bound.
        yield 'show all is still capped to the configured bound' => [
            (new DataTable('movies'))->pageLength(2000),
            -1,
            100,
        ];
    }

    /**
     * @return iterable<string, array{0: int, 1: int}>
     */
    public static function cappedLengths(): iterable
    {
        yield 'show all is capped' => [-1, 100];
        yield 'an oversized length is capped' => [999999, 100];
        yield 'a length within bounds is kept' => [25, 25];
    }

    private function createRuntime(?DataProviderInterface $provider = null): DataTableRuntime
    {
        return new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => $provider,
        );
    }
}
