<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataProvider;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
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
}
