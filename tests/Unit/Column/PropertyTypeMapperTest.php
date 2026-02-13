<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\PropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PropertyTypeMapperTest extends TestCase
{
    private PropertyTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new PropertyTypeMapper();
    }

    #[DataProvider('provideTypeMappings')]
    public function testMapType(string $propertyName, string $expectedColumnClass): void
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

    public function testNullTypeReturnsTextColumn(): void
    {
        $this->assertSame(TextColumn::class, $this->mapper->mapType(null));
    }

    public function testUntypedPropertyReturnsTextColumn(): void
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
