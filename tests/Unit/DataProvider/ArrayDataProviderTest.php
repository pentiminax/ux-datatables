<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\RowMapper\DefaultRowMapper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ArrayDataProvider::class)]
final class ArrayDataProviderTest extends TestCase
{
    #[Test]
    public function it_treats_non_positive_length_as_no_limit(): void
    {
        $provider = new ArrayDataProvider(
            [
                ['id' => 1],
                ['id' => 2],
                ['id' => 3],
            ],
            new DefaultRowMapper([TextColumn::new('id')]),
        );

        $result = $provider->fetchData(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 1,
            length: 0,
        ));

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(3, $result->recordsFiltered);
        $this->assertSame([
            ['id' => 2],
            ['id' => 3],
        ], iterator_to_array($result->data));
    }

    #[Test]
    public function it_maps_only_returned_rows_when_no_search_is_active(): void
    {
        $mapper = new CountingRowMapper();

        $provider = new ArrayDataProvider(
            [
                ['id' => 1],
                ['id' => 2],
                ['id' => 3],
                ['id' => 4],
                ['id' => 5],
            ],
            $mapper,
        );

        $result = $provider->fetchData(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 1,
            length: 2,
        ));

        $data = iterator_to_array($result->data);

        $this->assertSame(5, $result->recordsTotal);
        $this->assertSame(5, $result->recordsFiltered);
        $this->assertSame([
            ['id' => 2],
            ['id' => 3],
        ], $data);

        // Only the two returned rows are mapped, out-of-page rows are never mapped,
        // and each returned row is mapped exactly once.
        $this->assertSame(2, $mapper->calls);
        $this->assertSame([['id' => 2], ['id' => 3]], $mapper->mappedRows);
    }

    #[Test]
    public function it_maps_each_element_exactly_once_when_a_search_is_active(): void
    {
        $mapper = new CountingRowMapper();

        $provider = new ArrayDataProvider(
            [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
                ['id' => 3, 'name' => 'Alicia'],
                ['id' => 4, 'name' => 'Carol'],
            ],
            $mapper,
        );

        $result = $provider->fetchData(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 0,
            length: 10,
            search: new Search('ali', false),
        ));

        $data = iterator_to_array($result->data);

        $this->assertSame(4, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 3, 'name' => 'Alicia'],
        ], $data);

        // Each element is mapped exactly once (no double mapping for filter + output).
        $this->assertSame(4, $mapper->calls);
    }

    #[Test]
    public function it_treats_an_empty_search_value_as_no_search_and_maps_only_the_slice(): void
    {
        $mapper = new CountingRowMapper();

        $provider = new ArrayDataProvider(
            [
                ['id' => 1],
                ['id' => 2],
                ['id' => 3],
                ['id' => 4],
                ['id' => 5],
            ],
            $mapper,
        );

        // An unfiltered page load from Search::fromRequest() yields Search('', false).
        $result = $provider->fetchData(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 1,
            length: 2,
            search: new Search('', false),
        ));

        $data = iterator_to_array($result->data);

        $this->assertSame(5, $result->recordsTotal);
        $this->assertSame(5, $result->recordsFiltered);
        $this->assertSame([
            ['id' => 2],
            ['id' => 3],
        ], $data);

        // Empty search must behave like "no search": only the two returned rows
        // are mapped, out-of-page rows are never mapped.
        $this->assertSame(2, $mapper->calls);
        $this->assertSame([['id' => 2], ['id' => 3]], $mapper->mappedRows);
    }

    #[Test]
    public function it_supports_object_items_without_a_search(): void
    {
        $mapper = new CountingRowMapper();

        $provider = new ArrayDataProvider(
            [
                (object) ['id' => 1, 'name' => 'Alice'],
                (object) ['id' => 2, 'name' => 'Bob'],
                (object) ['id' => 3, 'name' => 'Carol'],
            ],
            $mapper,
        );

        $result = $provider->fetchData(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 1,
            length: 1,
        ));

        $data = iterator_to_array($result->data);

        $this->assertSame(3, $result->recordsTotal);
        $this->assertSame(3, $result->recordsFiltered);
        $this->assertSame([
            ['id' => 2, 'name' => 'Bob'],
        ], $data);

        // Only the single returned object is mapped.
        $this->assertSame(1, $mapper->calls);
    }

    #[Test]
    public function it_supports_object_items_with_a_search(): void
    {
        $mapper = new CountingRowMapper();

        $provider = new ArrayDataProvider(
            [
                (object) ['id' => 1, 'name' => 'Alice'],
                (object) ['id' => 2, 'name' => 'Bob'],
                (object) ['id' => 3, 'name' => 'Alicia'],
                (object) ['id' => 4, 'name' => 'Carol'],
            ],
            $mapper,
        );

        $result = $provider->fetchData(new DataTableRequest(
            draw: 1,
            columns: new Columns([]),
            start: 0,
            length: 10,
            search: new Search('ali', false),
        ));

        $data = iterator_to_array($result->data);

        $this->assertSame(4, $result->recordsTotal);
        $this->assertSame(2, $result->recordsFiltered);
        $this->assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 3, 'name' => 'Alicia'],
        ], $data);

        // Each object element is mapped exactly once.
        $this->assertSame(4, $mapper->calls);
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
