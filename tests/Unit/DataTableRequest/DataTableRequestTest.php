<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(DataTableRequest::class)]
final class DataTableRequestTest extends TestCase
{
    /**
     * @param list<array<string, mixed>> $order    the raw DataTables.net order payload
     * @param list<Order>                $expected the orders resolved against the columns
     */
    #[Test]
    #[DataProvider('provideOrderPayloads')]
    public function it_parses_ordering_from_request(array $order, array $expected): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest(['order' => $order]));

        $this->assertEquals($expected, $dataTableRequest->order);
    }

    public static function provideOrderPayloads(): iterable
    {
        yield 'multiple orders resolve their column names' => [
            [
                ['column' => 1, 'dir' => 'asc'],
                ['column' => 0, 'dir' => 'desc'],
            ],
            [
                new Order(column: 1, dir: 'asc', name: 'name'),
                new Order(column: 0, dir: 'desc', name: 'id'),
            ],
        ];

        yield 'no ordering at all' => [[], []];
    }

    #[Test]
    public function it_parses_all_properties_from_request(): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest([
            'draw'   => 5,
            'start'  => 20,
            'length' => 25,
            'order'  => [['column' => 2, 'dir' => 'desc']],
            'search' => ['value' => 'test', 'regex' => false],
        ]));

        $this->assertSame(5, $dataTableRequest->draw);
        $this->assertSame(20, $dataTableRequest->start);
        $this->assertSame(25, $dataTableRequest->length);
        $this->assertSame('test', $dataTableRequest->search->value);
        $this->assertEquals([new Order(column: 2, dir: 'desc', name: 'email')], $dataTableRequest->order);
    }

    #[Test]
    public function it_parses_filters_from_request(): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest([
            'filters' => [
                'name'      => 'john',
                'status'    => ['draft', 'published'],
                'createdAt' => ['from' => '2024-01-01', 'to' => '2024-12-31'],
            ],
        ]));

        $this->assertSame([
            'name'      => 'john',
            'status'    => ['draft', 'published'],
            'createdAt' => ['from' => '2024-01-01', 'to' => '2024-12-31'],
        ], $dataTableRequest->filters);
    }

    #[Test]
    public function it_defaults_filters_to_an_empty_array(): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest());

        $this->assertSame([], $dataTableRequest->filters);
    }

    /**
     * @param array<string, mixed> $overrides query parameters replacing the baseline payload
     */
    private static function createRequest(array $overrides = []): Request
    {
        return new Request(query: array_replace([
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'columns' => [
                ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true],
                ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
                ['data' => 'email', 'name' => 'email', 'searchable' => true, 'orderable' => true],
            ],
            'order'  => [],
            'search' => ['value' => '', 'regex' => false],
        ], $overrides));
    }
}
