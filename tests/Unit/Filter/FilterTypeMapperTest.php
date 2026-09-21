<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\DateRangeFilter;
use Pentiminax\UX\DataTables\Filter\FilterTypeMapper;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(FilterTypeMapper::class)]
final class FilterTypeMapperTest extends TestCase
{
    /**
     * @param class-string $expectedFilterClass
     */
    #[Test]
    #[TestWith(['name', TextFilter::class])]
    #[TestWith(['active', TernaryFilter::class])]
    #[TestWith(['status', ChoiceFilter::class])]
    #[TestWith(['createdAt', DateRangeFilter::class])]
    #[TestWith(['unrelated', TextFilter::class])]
    public function it_maps_a_declared_type_to_a_filter(string $property, string $expectedFilterClass): void
    {
        $type = (new \ReflectionProperty(TypedFixture::class, $property))->getType();

        $this->assertSame($expectedFilterClass, (new FilterTypeMapper())->mapType($type));
    }

    #[Test]
    public function it_falls_back_to_a_text_filter_without_a_type(): void
    {
        $this->assertSame(TextFilter::class, (new FilterTypeMapper())->mapType(null));
    }

    #[Test]
    #[TestWith(['count', 'int'])]
    #[TestWith(['ratio', 'float'])]
    public function it_rejects_a_numeric_type_no_filter_can_query(string $property, string $typeName): void
    {
        $type = (new \ReflectionProperty(TypedFixture::class, $property))->getType();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The filter "%s" cannot be guessed from its "%s" type', $property, $typeName));

        (new FilterTypeMapper())->mapType($type, $property);
    }
}

enum FixtureStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

final class TypedFixture
{
    public string $name          = '';
    public int $count            = 0;
    public float $ratio          = 0.0;
    public bool $active          = true;
    public FixtureStatus $status = FixtureStatus::Active;
    public \DateTimeImmutable $createdAt;
    public \stdClass $unrelated;
}
