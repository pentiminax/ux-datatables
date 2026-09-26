<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query;

use Pentiminax\UX\DataTables\Query\DoctrineSortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DoctrineSortDirection::class)]
final class DoctrineSortDirectionTest extends TestCase
{
    #[Test]
    public function it_keeps_string_directions_for_an_orm_that_only_accepts_strings(): void
    {
        $this->assertSame('asc', DoctrineSortDirection::from('asc', StringOrderQueryBuilder::class));
        $this->assertSame('ASC', DoctrineSortDirection::from('ASC', StringOrderQueryBuilder::class));
        $this->assertSame('desc', DoctrineSortDirection::from('desc', StringOrderQueryBuilder::class));
    }

    #[Test]
    public function it_maps_directions_to_the_enum_for_an_orm_that_accepts_it(): void
    {
        if (!enum_exists(\SortDirection::class)) {
            $this->markTestSkipped('\SortDirection needs PHP 8.6, doctrine/orm 3.7, or symfony/polyfill-php86.');
        }

        $this->assertSame(\SortDirection::Ascending, DoctrineSortDirection::from('asc', EnumOrderQueryBuilder::class));
        $this->assertSame(\SortDirection::Ascending, DoctrineSortDirection::from('ASC', EnumOrderQueryBuilder::class));
        $this->assertSame(\SortDirection::Descending, DoctrineSortDirection::from('desc', EnumOrderQueryBuilder::class));
    }
}

final class StringOrderQueryBuilder
{
    public function addOrderBy(string $sort, ?string $order = null): void
    {
    }
}

final class EnumOrderQueryBuilder
{
    public function addOrderBy(string $sort, \SortDirection|string|null $order = null): void
    {
    }
}
