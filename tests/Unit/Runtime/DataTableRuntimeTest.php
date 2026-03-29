<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Runtime;

use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
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
    public function test_give_n_no_handled_request_whe_n_get_http_request_the_n_null_is_returned(): void
    {
        $runtime = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
        );

        $this->assertNull($runtime->getHttpRequest());
    }

    #[Test]
    public function test_give_n_handled_request_whe_n_get_http_request_the_n_same_request_is_returned(): void
    {
        $runtime = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
        );
        $request = new Request(query: ['draw' => 7, 'genre' => 'sci-fi']);

        $runtime->handleRequest($request);

        $this->assertSame($request, $runtime->getHttpRequest());
    }

    #[Test]
    public function it_returns_an_empty_response_when_no_request_has_been_handled(): void
    {
        $runtime = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
        );

        $response = $runtime->getResponse();

        $this->assertSame([
            'draw'            => 1,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function it_returns_an_empty_response_when_a_request_is_handled_without_a_provider(): void
    {
        $runtime = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => null,
        );
        $runtime->handleRequest(new Request(query: ['draw' => 7]));

        $response = $runtime->getResponse();

        $this->assertSame([
            'draw'            => 7,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function it_builds_a_response_from_the_provider_result(): void
    {
        $provider = new class implements DataProviderInterface {
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
        };

        $runtime = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => $provider,
        );
        $runtime->handleRequest(new Request(query: ['draw' => 3]));

        $response = $runtime->getResponse();

        $this->assertSame([
            'draw'            => 3,
            'recordsTotal'    => 10,
            'recordsFiltered' => 4,
            'data'            => [
                ['id' => 1, 'name' => 'Alien'],
                ['id' => 2, 'name' => 'Heat'],
            ],
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function it_caches_the_resolved_provider(): void
    {
        $provider     = $this->createMock(DataProviderInterface::class);
        $factoryCalls = 0;
        $runtime      = new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static function () use ($provider, &$factoryCalls): ?DataProviderInterface {
                ++$factoryCalls;

                return $provider;
            },
        );

        $firstProvider  = $runtime->getDataProvider();
        $secondProvider = $runtime->getDataProvider();

        $this->assertSame($provider, $firstProvider);
        $this->assertSame($provider, $secondProvider);
        $this->assertSame(1, $factoryCalls);
    }
}
