<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\PropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PropertyTypeMapper::class)]
final class PropertyTypeMapperTest extends TestCase
{
    private PropertyTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PropertyTypeMapper();
    }

    #[Test]
    #[DataProvider('provideTypeMappings')]
    public function it_maps_type(string $propertyName, string $expectedColumnClass): void
    {
        $reflection = new \ReflectionProperty(TypeMapperFixture::class, $propertyName);
        $type       = $reflection->getType();

        $this->assertSame($expectedColumnClass, $this->mapper->mapType($type));
    }

    /**
     * @return iterable<string, array{0: string, 1: class-string}>
     */
    public static function provideTypeMappings(): iterable
    {
        yield 'bool' => ['active', BooleanColumn::class];
        yield 'int' => ['count', NumberColumn::class];
        yield 'float' => ['price', NumberColumn::class];
        yield 'string' => ['name', TextColumn::class];
        yield 'DateTime' => ['createdAt', DateColumn::class];
        yield 'DateTimeImmutable' => ['updatedAt', DateColumn::class];
        yield 'DateTimeInterface' => ['deletedAt', DateColumn::class];
        yield 'object' => ['metadata', TextColumn::class];
    }

    #[Test]
    public function it_returns_text_column_for_null_type(): void
    {
        $this->assertSame(TextColumn::class, $this->mapper->mapType(null));
    }

    #[Test]
    public function it_returns_text_column_for_untyped_property(): void
    {
        $reflection = new \ReflectionProperty(TypeMapperFixture::class, 'untyped');
        $type       = $reflection->getType();

        $this->assertNull($type);
        $this->assertSame(TextColumn::class, $this->mapper->mapType(null));
    }
}

final class TypeMapperFixture
{
    public bool $active = false;
    public int $count   = 0;
    public float $price = 0.0;
    public string $name = '';
    public \DateTime $createdAt;
    public \DateTimeImmutable $updatedAt;
    public \DateTimeInterface $deletedAt;
    public \stdClass $metadata;
    public $untyped;
}
