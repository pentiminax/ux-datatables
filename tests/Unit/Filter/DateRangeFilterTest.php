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

    #[Test]
    #[DataProvider('provideTypedDateColumns')]
    public function it_binds_parsed_dates_with_the_doctrine_type_on_a_date_column(string $doctrineType): void
    {
        DateRangeFilter::new('createdAt')->apply(
            $this->createScalarFieldQueryBuilder($doctrineType),
            ['from' => ' 2024-01-01 ', 'to' => '2024-12-31'],
            'e',
        );

        $this->assertSame(
            ['e.createdAt >= :filter_createdAt_from', 'e.createdAt <= :filter_createdAt_to'],
            $this->capturedWhere,
        );
        $this->assertSame($doctrineType, $this->capturedParamTypes['filter_createdAt_from']);
        $this->assertSame($doctrineType, $this->capturedParamTypes['filter_createdAt_to']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->capturedParams['filter_createdAt_from']);
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->capturedParams['filter_createdAt_to']);
        $this->assertSame('2024-01-01', $this->capturedParams['filter_createdAt_from']->format('Y-m-d'));
        $this->assertSame('2024-12-31', $this->capturedParams['filter_createdAt_to']->format('Y-m-d'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideTypedDateColumns(): iterable
    {
        yield 'date' => ['date'];
        yield 'datetime' => ['datetime'];
        yield 'datetime_immutable' => ['datetime_immutable'];
    }

    #[Test]
    public function it_skips_unparseable_bounds_on_a_date_column(): void
    {
        DateRangeFilter::new('createdAt')->apply(
            $this->createScalarFieldQueryBuilder('date'),
            ['from' => 'not-a-date', 'to' => 'also-bad'],
            'e',
        );

        $this->assertSame([], $this->capturedWhere);
        $this->assertSame([], $this->capturedParams);
    }

    #[Test]
    public function it_applies_only_the_parseable_bound_on_a_date_column(): void
    {
        DateRangeFilter::new('createdAt')->apply(
            $this->createScalarFieldQueryBuilder('datetime'),
            ['from' => '2024-01-01', 'to' => 'garbage'],
            'e',
        );

        $this->assertSame(['e.createdAt >= :filter_createdAt_from'], $this->capturedWhere);
        $this->assertArrayHasKey('filter_createdAt_from', $this->capturedParams);
        $this->assertArrayNotHasKey('filter_createdAt_to', $this->capturedParams);
        $this->assertSame('datetime', $this->capturedParamTypes['filter_createdAt_from']);
        $this->assertSame('2024-01-01', $this->capturedParams['filter_createdAt_from']->format('Y-m-d'));
    }
}
