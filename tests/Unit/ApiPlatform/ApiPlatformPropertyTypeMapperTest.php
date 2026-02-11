<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PropertyInfo\Type as LegacyType;

class ApiPlatformPropertyTypeMapperTest extends TestCase
{
    private ApiPlatformPropertyTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ApiPlatformPropertyTypeMapper();
    }

    #[DataProvider('provideLegacyTypeMappings')]
    public function testMapLegacyType(LegacyType $type, string $expectedColumnClass): void
    {
        $this->assertSame($expectedColumnClass, $this->mapper->mapType($type));
    }

    /**
     * @return iterable<string, array{0: LegacyType, 1: class-string}>
     */
    public static function provideLegacyTypeMappings(): iterable
    {
        yield 'bool' => [new LegacyType(LegacyType::BUILTIN_TYPE_BOOL), BooleanColumn::class];
        yield 'int' => [new LegacyType(LegacyType::BUILTIN_TYPE_INT), NumberColumn::class];
        yield 'float' => [new LegacyType(LegacyType::BUILTIN_TYPE_FLOAT), NumberColumn::class];
        yield 'string' => [new LegacyType(LegacyType::BUILTIN_TYPE_STRING), TextColumn::class];
        yield 'DateTime' => [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, \DateTime::class), DateColumn::class];
        yield 'DateTimeImmutable' => [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, \DateTimeImmutable::class), DateColumn::class];
        yield 'object-not-date' => [new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, \stdClass::class), TextColumn::class];
        yield 'array' => [new LegacyType(LegacyType::BUILTIN_TYPE_ARRAY), TextColumn::class];
    }

    public function testNullTypeFallsBackToTextColumn(): void
    {
        $this->assertSame(TextColumn::class, $this->mapper->mapType(null));
    }

    public function testCreateColumnReturnsCorrectInstance(): void
    {
        $type = new LegacyType(LegacyType::BUILTIN_TYPE_INT);
        $column = $this->mapper->createColumn('price', 'Price', $type);

        $this->assertInstanceOf(NumberColumn::class, $column);
        $this->assertSame('price', $column->getName());

        $data = $column->jsonSerialize();
        $this->assertSame('Price', $data['title']);
    }

    public function testCreateColumnWithBoolType(): void
    {
        $type = new LegacyType(LegacyType::BUILTIN_TYPE_BOOL);
        $column = $this->mapper->createColumn('active', 'Active', $type);

        $this->assertInstanceOf(BooleanColumn::class, $column);
    }

    public function testCreateColumnWithDateTimeType(): void
    {
        $type = new LegacyType(LegacyType::BUILTIN_TYPE_OBJECT, false, \DateTimeImmutable::class);
        $column = $this->mapper->createColumn('createdAt', 'Created At', $type);

        $this->assertInstanceOf(DateColumn::class, $column);
    }
}
