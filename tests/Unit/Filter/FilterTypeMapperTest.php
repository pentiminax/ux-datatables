<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\DateRangeFilter;
use Pentiminax\UX\DataTables\Filter\FilterTypeMapper;
use Pentiminax\UX\DataTables\Filter\NumberRangeFilter;
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
    #[TestWith(['count', NumberRangeFilter::class])]
    #[TestWith(['ratio', NumberRangeFilter::class])]
    #[TestWith(['unrelated', TextFilter::class])]
    public function it_maps_a_declared_type_to_a_filter(string $property, string $expectedFilterClass): void
    {
        $type = (new \ReflectionProperty(TypedFixture::class, $property))->getType();

        $this->assertSame($expectedFilterClass, (new FilterTypeMapper())->mapType($type));
    }

    /**
     * @deprecated covers the ignored $name argument kept until 2.0
     */
    #[Test]
    public function it_still_accepts_the_deprecated_name_argument(): void
    {
        $type = (new \ReflectionProperty(TypedFixture::class, 'count'))->getType();

        $messages = [];
        set_error_handler(static function (int $level, string $message) use (&$messages): bool {
            $messages[] = $message;

            return true;
        }, \E_USER_DEPRECATED);

        try {
            $filterClass = (new FilterTypeMapper())->mapType($type, 'count');
        } finally {
            restore_error_handler();
        }

        $this->assertSame(NumberRangeFilter::class, $filterClass);
        $this->assertCount(1, $messages);
        $this->assertStringContainsString('Since pentiminax/ux-datatables 1.3: Passing the $name argument', $messages[0]);
    }

    #[Test]
    public function it_falls_back_to_a_text_filter_without_a_type(): void
    {
        $this->assertSame(TextFilter::class, (new FilterTypeMapper())->mapType(null));
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
