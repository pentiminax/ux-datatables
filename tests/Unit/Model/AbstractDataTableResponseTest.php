<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTableResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableResponseTest extends TestCase
{
    #[Test]
    public function it_returns_an_empty_response_when_no_request_has_been_handled(): void
    {
        $table    = new ResponseTestTable();
        $response = $table->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame([
            'draw'            => 1,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
        ], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function it_builds_a_response_from_the_data_provider_result(): void
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

        $table = new ResponseTestTable($provider);
        $table->handleRequest(new Request(query: ['draw' => 3]));

        $response = $table->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
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

    public function getDataProvider(): ?DataProviderInterface
    {
        return $this->provider;
    }
}
