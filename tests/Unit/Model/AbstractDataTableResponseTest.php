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

    #[Test]
    public function it_returns_an_empty_response_when_a_request_is_handled_without_a_provider(): void
    {
        $table = new ResponseTestTable();
        $table->handleRequest(new Request(query: ['draw' => 7]));

        $response = $table->getResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame([
            'draw'            => 7,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
        ], json_decode((string) $response->getContent(), true));
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
