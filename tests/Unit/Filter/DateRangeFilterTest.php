<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\DateRangeFilter;
use Pentiminax\UX\DataTables\Tests\Support\BuildsFilterQueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(DateRangeFilter::class)]
final class DateRangeFilterTest extends TestCase
{
    use BuildsFilterQueryBuilder;

    #[Test]
    public function it_serializes_its_definition(): void
    {
        $filter = DateRangeFilter::new('createdAt')->label('Created');

        $this->assertSame([
            'name'  => 'createdAt',
            'type'  => 'dateRange',
            'label' => 'Created',
        ], $filter->jsonSerialize());
    }

    /**
     * @param array<string, string> $value
     * @param list<string>          $expectedWhere
     * @param array<string, mixed>  $expectedParams
     */
    #[Test]
    #[DataProvider('provideBounds')]
    public function it_applies_the_provided_bounds(array $value, array $expectedWhere, array $expectedParams): void
    {
        $this->assertFilterProduces(DateRangeFilter::new('createdAt'), $value, $expectedWhere, $expectedParams);
    }

    /**
     * @return iterable<string, array{array<string, string>, list<string>, array<string, mixed>}>
     */
    public static function provideBounds(): iterable
    {
        yield 'both bounds' => [
            ['from' => '2024-01-01', 'to' => '2024-12-31'],
            ['e.createdAt >= :filter_createdAt_from', 'e.createdAt <= :filter_createdAt_to'],
            ['filter_createdAt_from' => '2024-01-01', 'filter_createdAt_to' => '2024-12-31'],
        ];

        yield 'lower bound only' => [
            ['from' => '2024-01-01'],
            ['e.createdAt >= :filter_createdAt_from'],
            ['filter_createdAt_from' => '2024-01-01'],
        ];
    }
}
