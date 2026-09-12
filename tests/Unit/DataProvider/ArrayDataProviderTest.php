<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Column as RequestColumn;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ArrayDataProvider::class)]
final class ArrayDataProviderTest extends TestCase
{
    /**
     * @param list<mixed>      $rows
     * @param list<array>      $expectedData
     * @param list<array>|null $expectedMappedRows the rows handed to the mapper, when it matters
     */
    #[Test]
    #[DataProvider('fetch_cases')]
    public function it_fetches_the_expected_page(
        array $rows,
        DataTableRequest $request,
        int $expectedTotal,
        int $expectedFiltered,
        array $expectedData,
        int $expectedMapperCalls,
        ?array $expectedMappedRows = null,
    ): void {
        $mapper = new CountingRowMapper();

        $result = (new ArrayDataProvider($rows, $mapper))->fetchData($request);

        $this->assertSame($expectedTotal, $result->recordsTotal);
        $this->assertSame($expectedFiltered, $result->recordsFiltered);
        $this->assertSame($expectedData, iterator_to_array($result->data));
        $this->assertSame($expectedMapperCalls, $mapper->calls);

        if (null !== $expectedMappedRows) {
            $this->assertSame($expectedMappedRows, $mapper->mappedRows);
        }
    }

    /**
     * @return iterable<string, array{list<mixed>, DataTableRequest, int, int, list<array>, int, 6?: list<array>}>
     */
    public static function fetch_cases(): iterable
    {
        $numbered = [['id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]];

        yield 'non-positive length means no limit' => [
            [['id' => 1], ['id' => 2], ['id' => 3]],
            self::request(start: 1, length: 0),
            3,
            3,
            [['id' => 2], ['id' => 3]],
            2,
        ];

        // Out-of-page rows are never mapped, and each returned row is mapped exactly once.
        yield 'only the returned rows are mapped' => [
            $numbered,
            self::request(start: 1, length: 2),
            5,
            5,
            [['id' => 2], ['id' => 3]],
            2,
            [['id' => 2], ['id' => 3]],
        ];

        // An unfiltered page load from Search::fromRequest() yields Search('', false)
        // and must behave exactly like "no search".
        yield 'empty search value behaves like no search' => [
            $numbered,
            self::request(start: 1, length: 2, search: new Search('', false)),
            5,
            5,
            [['id' => 2], ['id' => 3]],
            2,
            [['id' => 2], ['id' => 3]],
        ];

        yield 'object items' => [
            [(object) ['id' => 1, 'name' => 'Alice'], (object) ['id' => 2, 'name' => 'Bob'], (object) ['id' => 3, 'name' => 'Carol']],
            self::request(start: 1, length: 1),
            3,
            3,
            [['id' => 2, 'name' => 'Bob']],
            1,
        ];
    }

    #[Test]
    public function it_keeps_working_without_configured_columns(): void
    {
        $mapper = new CountingRowMapper();

        // Two-argument construction: no columns means nothing is searchable or orderable,
        // but the request must still page correctly instead of raising.
        $result = (new ArrayDataProvider(self::people(), $mapper))->fetchData(self::request(
            start: 1,
            length: 2,
            search: new Search('ali', false),
            order: [new Order(0, 'desc', 'name')],
        ));

        $this->assertSame(4, $result->recordsTotal);
        $this->assertSame(4, $result->recordsFiltered);
        $this->assertSame(
            [['id' => 2, 'name' => 'Bob', 'score' => 30], ['id' => 3, 'name' => 'alicia', 'score' => null]],
            iterator_to_array($result->data),
        );
        $this->assertSame(2, $mapper->calls);
    }

    /**
     * @param list<array{id: int, name: string|null, score: int|null}> $expected
     */
    #[Test]
    #[DataProvider('order_cases')]
    public function it_orders_rows_by_the_requested_column(string $columnName, string $direction, array $expected): void
    {
        $result = (new ArrayDataProvider(self::people(), new CountingRowMapper(), self::columns()))->fetchData(self::request(
            start: 0,
            length: 10,
            order: [new Order(0, $direction, $columnName)],
        ));

        $this->assertSame($expected, iterator_to_array($result->data));
    }

    /**
     * @return iterable<string, array{string, string, list<array>}>
     */
    public static function order_cases(): iterable
    {
        $alice  = ['id' => 1, 'name' => 'Alice', 'score' => 10];
        $bob    = ['id' => 2, 'name' => 'Bob', 'score' => 30];
        $alicia = ['id' => 3, 'name' => 'alicia', 'score' => null];
        $carol  = ['id' => 4, 'name' => null, 'score' => 20];

        // strnatcasecmp: 'alicia' sorts next to 'Alice', not after 'Bob'.
        yield 'strings ascending, nulls last' => ['name', 'asc', [$alice, $alicia, $bob, $carol]];
        yield 'strings descending, nulls last' => ['name', 'desc', [$bob, $alicia, $alice, $carol]];
        yield 'numbers ascending, nulls last' => ['score', 'asc', [$alice, $carol, $bob, $alicia]];
        yield 'numbers descending, nulls last' => ['score', 'desc', [$bob, $carol, $alice, $alicia]];
    }

    #[Test]
    public function it_applies_the_global_search_on_globally_searchable_columns_only(): void
    {
        $columns    = self::columns();
        $columns[1] = TextColumn::new('name')->disableGlobalSearch();

        $result = (new ArrayDataProvider(self::people(), new CountingRowMapper(), $columns))->fetchData(
            self::request(start: 0, length: 10, search: new Search('ali', false)),
        );

        $this->assertSame(0, $result->recordsFiltered);
        $this->assertSame([], iterator_to_array($result->data));
    }

    #[Test]
    public function it_applies_column_searches_cumulatively(): void
    {
        $request = self::request(
            start: 0,
            length: 10,
            requestColumns: [
                self::requestColumn('name', new Search('ali', false)),
                self::requestColumn('score', new Search('10', false)),
            ],
        );

        $result = (new ArrayDataProvider(self::people(), new CountingRowMapper(), self::columns()))->fetchData($request);

        // Alice matches both; alicia matches the name only and has no score.
        $this->assertSame(1, $result->recordsFiltered);
        $this->assertSame([['id' => 1, 'name' => 'Alice', 'score' => 10]], iterator_to_array($result->data));
    }

    #[Test]
    public function it_trims_the_search_term_and_ignores_case(): void
    {
        $provider = new ArrayDataProvider(self::people(), new CountingRowMapper(), self::columns());

        $global = $provider->fetchData(self::request(start: 0, length: 10, search: new Search('  AL ', false)));
        $this->assertSame(2, $global->recordsFiltered);

        $perColumn = $provider->fetchData(self::request(
            start: 0,
            length: 10,
            requestColumns: [self::requestColumn('name', new Search(' ALICIA ', false))],
        ));
        $this->assertSame(1, $perColumn->recordsFiltered);
        $this->assertSame([['id' => 3, 'name' => 'alicia', 'score' => null]], iterator_to_array($perColumn->data));
    }

    #[Test]
    public function it_does_not_search_non_searchable_columns(): void
    {
        $columns    = self::columns();
        $columns[1] = TextColumn::new('name')->setSearchable(false);

        $result = (new ArrayDataProvider(self::people(), new CountingRowMapper(), $columns))->fetchData(
            self::request(start: 0, length: 10, search: new Search('ali', false)),
        );

        $this->assertSame(0, $result->recordsFiltered);
    }

    /**
     * A column without a matching source property (an ActionColumn, a TemplateColumn) reads as
     * null and never matches, where the previous mapped-row search had to guard against casting
     * the nested action array that such a column puts on the mapped row.
     */
    #[Test]
    public function it_ignores_columns_without_a_readable_source_value(): void
    {
        $columns = [...self::columns(), TextColumn::new('actions')];

        $result = (new ArrayDataProvider(self::people(), new CountingRowMapper(), $columns))->fetchData(
            self::request(start: 0, length: 10, search: new Search('actions', false)),
        );

        $this->assertSame(0, $result->recordsFiltered);
    }

    #[Test]
    public function it_throws_when_the_request_carries_column_control_searches(): void
    {
        $request = self::request(
            start: 0,
            length: 10,
            requestColumns: [
                new RequestColumn(
                    data: 'name',
                    name: 'name',
                    searchable: true,
                    orderable: true,
                    columnControl: new ColumnControl(new ColumnControlSearch('Alice', ColumnControlLogic::Equal, 'text')),
                ),
            ],
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ArrayDataProvider does not support ColumnControl searches or configured Filters.');

        (new ArrayDataProvider(self::people(), new CountingRowMapper(), self::columns()))->fetchData($request);
    }

    #[Test]
    public function it_throws_when_the_request_carries_configured_filters(): void
    {
        $request = self::request(start: 0, length: 10, filters: ['status' => 'active']);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('ArrayDataProvider does not support ColumnControl searches or configured Filters.');

        (new ArrayDataProvider(self::people(), new CountingRowMapper(), self::columns()))->fetchData($request);
    }

    #[Test]
    public function it_ignores_empty_filter_values(): void
    {
        $result = (new ArrayDataProvider(self::people(), new CountingRowMapper(), self::columns()))->fetchData(
            self::request(start: 0, length: 2, filters: ['status' => '', 'tags' => []]),
        );

        $this->assertSame(4, $result->recordsFiltered);
    }

    /**
     * @return list<array{id: int, name: string|null, score: int|null}>
     */
    private static function people(): array
    {
        return [
            ['id' => 1, 'name' => 'Alice', 'score' => 10],
            ['id' => 2, 'name' => 'Bob', 'score' => 30],
            ['id' => 3, 'name' => 'alicia', 'score' => null],
            ['id' => 4, 'name' => null, 'score' => 20],
        ];
    }

    /**
     * @return list<ColumnInterface>
     */
    private static function columns(): array
    {
        return [
            NumberColumn::new('id'),
            TextColumn::new('name'),
            NumberColumn::new('score'),
        ];
    }

    private static function requestColumn(string $name, ?Search $search = null): RequestColumn
    {
        return new RequestColumn(data: $name, name: $name, searchable: true, orderable: true, search: $search);
    }

    /**
     * @param list<Order>          $order
     * @param list<RequestColumn>  $requestColumns
     * @param array<string, mixed> $filters
     */
    private static function request(
        int $start,
        int $length,
        ?Search $search = null,
        array $order = [],
        array $requestColumns = [],
        array $filters = [],
    ): DataTableRequest {
        $indexed = [];
        foreach ($requestColumns as $column) {
            $indexed[$column->name] = $column;
        }

        return new DataTableRequest(
            draw: 1,
            columns: new Columns($indexed),
            start: $start,
            length: $length,
            search: $search,
            order: $order,
            filters: $filters,
        );
    }
}

/**
 * @internal
 */
final class CountingRowMapper implements RowMapperInterface
{
    public int $calls = 0;

    /** @var array<int, array> */
    public array $mappedRows = [];

    public function map(mixed $row): array
    {
        ++$this->calls;

        $mapped             = (array) $row;
        $this->mappedRows[] = $mapped;

        return $mapped;
    }
}
