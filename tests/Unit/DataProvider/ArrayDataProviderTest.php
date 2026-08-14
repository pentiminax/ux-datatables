<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
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

        $named = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
            ['id' => 3, 'name' => 'Alicia'],
            ['id' => 4, 'name' => 'Carol'],
        ];

        yield 'non-positive length means no limit' => [
            [['id' => 1], ['id' => 2], ['id' => 3]],
            self::request(start: 1, length: 0),
            3,
            3,
            [['id' => 2], ['id' => 3]],
            2,
        ];

        // Without a search, only the two returned rows are mapped: out-of-page rows
        // are never mapped, and each returned row is mapped exactly once.
        yield 'no search maps only the returned rows' => [
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

        // Every element is mapped exactly once (no double mapping for filter + output).
        yield 'search maps each element exactly once' => [
            $named,
            self::request(start: 0, length: 10, search: new Search('ali', false)),
            4,
            2,
            [['id' => 1, 'name' => 'Alice'], ['id' => 3, 'name' => 'Alicia']],
            4,
        ];

        // Three rows match 'ali'; the page boundary applies to the matched set, so
        // start: 1, length: 1 selects the second match (Alicia), not the first or all.
        yield 'search pagination applies to the matched rows' => [
            [...$named, ['id' => 5, 'name' => 'Alina']],
            self::request(start: 1, length: 1, search: new Search('ali', false)),
            5,
            3,
            [['id' => 3, 'name' => 'Alicia']],
            5,
        ];

        yield 'object items without a search' => [
            [(object) ['id' => 1, 'name' => 'Alice'], (object) ['id' => 2, 'name' => 'Bob'], (object) ['id' => 3, 'name' => 'Carol']],
            self::request(start: 1, length: 1),
            3,
            3,
            [['id' => 2, 'name' => 'Bob']],
            1,
        ];

        yield 'object items with a search' => [
            array_map(static fn (array $row): object => (object) $row, $named),
            self::request(start: 0, length: 10, search: new Search('ali', false)),
            4,
            2,
            [['id' => 1, 'name' => 'Alice'], ['id' => 3, 'name' => 'Alicia']],
            4,
        ];
    }

    private static function request(int $start, int $length, ?Search $search = null): DataTableRequest
    {
        return new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: $start,
            length: $length,
            search: $search,
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
