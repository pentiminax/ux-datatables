<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
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
