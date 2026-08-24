<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Column::class)]
final class ColumnTest extends TestCase
{
    #[Test]
    #[DataProvider('provideSearchValues')]
    public function it_decodes_search_values(?string $searchValue, array $expected): void
    {
        $column = self::createColumn($searchValue);

        $this->assertSame($expected, $column->searchValues());
    }

    public static function provideSearchValues(): iterable
    {
        yield 'no search at all' => [null, []];
        yield 'blank search' => ['   ', []];
        yield 'plain scalar value' => ['PRO', ['PRO']];
        yield 'json array of values' => ['["PRO","PAR"]', ['PRO', 'PAR']];
        yield 'json array trims and drops empty entries' => ['[" PRO ", "", "PAR"]', ['PRO', 'PAR']];
        yield 'json scalar falls back to raw value' => ['"PRO"', ['"PRO"']];
        yield 'invalid json falls back to raw value' => ['[not json', ['[not json']];
    }

    private static function createColumn(?string $searchValue): Column
    {
        return new Column(
            data: 'category',
            name: 'category',
            searchable: true,
            orderable: false,
            search: null === $searchValue ? null : new Search(value: $searchValue, regex: false),
        );
    }
}
