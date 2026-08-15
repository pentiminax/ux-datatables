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

    private function createRuntime(?DataProviderInterface $provider = null): DataTableRuntime
    {
        return new DataTableRuntime(
            table: new DataTable('movies'),
            dataProviderFactory: static fn (): ?DataProviderInterface => $provider,
        );
    }
}
