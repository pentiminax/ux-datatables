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
     * DataTable::ajax()'s $type parameter lets an app configure a body-carrying method;
     * DataTables then puts the same parameters in the request body instead of the query
     * string. Regression test: fromRequest() used to hardcode $request->query, so a
     * POST/PUT/PATCH-configured table always parsed an empty parameter set.
     */
    #[Test]
    #[DataProvider('provideBodyCarryingMethods')]
    public function it_parses_all_properties_from_a_body_carrying_request(string $method): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest([
            'draw'   => 5,
            'start'  => 20,
            'length' => 25,
            'order'  => [['column' => 2, 'dir' => 'desc']],
            'search' => ['value' => 'test', 'regex' => false],
        ], $method));

        $this->assertSame(5, $dataTableRequest->draw);
        $this->assertSame(20, $dataTableRequest->start);
        $this->assertSame(25, $dataTableRequest->length);
        $this->assertSame('test', $dataTableRequest->search->value);
        $this->assertEquals([new Order(column: 2, dir: 'desc', name: 'email')], $dataTableRequest->order);
    }

    public static function provideBodyCarryingMethods(): iterable
    {
        yield 'POST' => ['POST'];
        yield 'PUT' => ['PUT'];
        yield 'PATCH' => ['PATCH'];
    }

    #[Test]
    public function it_ignores_query_string_parameters_on_a_post_request(): void
    {
        $request = Request::create('/ajax?draw=99', 'POST', array_replace([
            'draw'    => 1,
            'start'   => 0,
            'length'  => 10,
            'columns' => [],
            'order'   => [],
            'search'  => ['value' => '', 'regex' => false],
        ], ['draw' => 5]));

        $dataTableRequest = DataTableRequest::fromRequest($request);

        $this->assertSame(5, $dataTableRequest->draw);
    }

    /**
     * Unlike POST/PUT/PATCH, DataTables' client-side ajax() helper moves DELETE's
     * parameters onto the URL by default (some servers reject a request body on DELETE),
     * so a DELETE-configured table must still parse from the query string.
     */
    #[Test]
    public function it_parses_all_properties_from_a_delete_request(): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest([
            'draw'   => 5,
            'start'  => 20,
            'length' => 25,
            'order'  => [['column' => 2, 'dir' => 'desc']],
            'search' => ['value' => 'test', 'regex' => false],
        ], 'DELETE'));

        $this->assertSame(5, $dataTableRequest->draw);
        $this->assertSame(20, $dataTableRequest->start);
        $this->assertSame(25, $dataTableRequest->length);
        $this->assertSame('test', $dataTableRequest->search->value);
        $this->assertEquals([new Order(column: 2, dir: 'desc', name: 'email')], $dataTableRequest->order);
    }

    #[Test]
    #[DataProvider('providePageLengths')]
    public function it_resolves_page_length(int $length, int $default, int $expected): void
    {
        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest(['length' => $length]));

        $this->assertSame($expected, $dataTableRequest->pageLength($default));
    }

    public static function providePageLengths(): iterable
    {
        yield 'falls back to the default when DataTables sends 0 (its "show all")' => [0, 25, 25];
        yield 'falls back to the default when negative' => [-1, 25, 25];
        yield 'keeps a positive length as-is' => [10, 25, 10];
        yield 'honors a custom default' => [0, 100, 100];
    }

    #[Test]
    #[DataProvider('provideSearchTerms')]
    public function it_resolves_search_term(?string $rawSearch, ?string $expected): void
    {
        $overrides = null === $rawSearch ? [] : ['search' => ['value' => $rawSearch, 'regex' => false]];

        $dataTableRequest = DataTableRequest::fromRequest(self::createRequest($overrides));

        $this->assertSame($expected, $dataTableRequest->searchTerm());
    }

    public static function provideSearchTerms(): iterable
    {
        yield 'no search parameter at all' => [null, null];
        yield 'empty search value' => ['', null];
        yield 'blank search value' => ['   ', null];
        yield 'trims surrounding whitespace' => ['  john  ', 'john'];
        yield 'preserves the literal zero' => ['0', '0'];
    }

    /**
     * @param array<string, mixed> $overrides query (or, for a body-carrying method, request
     *                                        body) parameters replacing the baseline payload
     */
    private static function createRequest(array $overrides = [], string $method = 'GET'): Request
    {
        $parameters = array_replace([
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
        ], $overrides);

        return \in_array($method, ['POST', 'PUT', 'PATCH'], true)
            ? new Request(request: $parameters, server: ['REQUEST_METHOD' => $method])
            : new Request(query: $parameters, server: ['REQUEST_METHOD' => $method]);
    }
}
