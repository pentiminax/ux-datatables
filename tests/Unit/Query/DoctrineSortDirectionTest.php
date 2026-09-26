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
    public function it_maps_directions_to_the_enum_the_installed_orm_accepts(): void
    {
        $this->assertSame(\SortDirection::Ascending, DoctrineSortDirection::from('asc'));
        $this->assertSame(\SortDirection::Ascending, DoctrineSortDirection::from('ASC'));
        $this->assertSame(\SortDirection::Descending, DoctrineSortDirection::from('desc'));
    }
}
