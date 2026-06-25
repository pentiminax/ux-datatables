<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\DateRangeFilter;
use PHPUnit\Framework\Attributes\CoversClass;
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

    #[Test]
    public function it_applies_both_bounds(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        DateRangeFilter::new('createdAt')->apply($qb, ['from' => '2024-01-01', 'to' => '2024-12-31'], 'e');

        $this->assertSame([
            'e.createdAt >= :filter_createdAt_from',
            'e.createdAt <= :filter_createdAt_to',
        ], $this->capturedWhere);
        $this->assertSame([
            'filter_createdAt_from' => '2024-01-01',
            'filter_createdAt_to'   => '2024-12-31',
        ], $this->capturedParams);
    }

    #[Test]
    public function it_applies_only_the_provided_bound(): void
    {
        $qb = $this->createScalarFieldQueryBuilder();

        DateRangeFilter::new('createdAt')->apply($qb, ['from' => '2024-01-01'], 'e');

        $this->assertSame(['e.createdAt >= :filter_createdAt_from'], $this->capturedWhere);
    }
}
