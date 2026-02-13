<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

class ApiPlatformPropertyTypeMapperTest extends TestCase
{
    private ApiPlatformPropertyTypeMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ApiPlatformPropertyTypeMapper();
    }

    #[DataProvider('provideTypeMappings')]
    public function testMapType(Type $type, string $expectedColumnClass): void
    {
        $this->assertSame($expectedColumnClass, $this->mapper->mapType($type));
    }

    /**
     * @return iterable<string, array{0: Type, 1: class-string}>
     */
    public static function provideTypeMappings(): iterable
    {
        yield 'bool' => [Type::bool(), BooleanColumn::class];
        yield 'int' => [Type::int(), NumberColumn::class];
        yield 'float' => [Type::float(), NumberColumn::class];
        yield 'string' => [Type::string(), TextColumn::class];
        yield 'DateTime' => [Type::object(\DateTime::class), DateColumn::class];
        yield 'DateTimeImmutable' => [Type::object(\DateTimeImmutable::class), DateColumn::class];
        yield 'object-not-date' => [Type::object(\stdClass::class), TextColumn::class];
        yield 'array' => [Type::array(), TextColumn::class];
    }

    public function testNullTypeFallsBackToTextColumn(): void
    {
        $this->assertSame(TextColumn::class, $this->mapper->mapType(null));
    }

    public function testCreateColumnReturnsCorrectInstance(): void
    {
        $type   = Type::int();
        $column = $this->mapper->createColumn('price', 'Price', $type);

        $this->assertInstanceOf(NumberColumn::class, $column);
        $this->assertSame('price', $column->getName());

        $data = $column->jsonSerialize();
        $this->assertSame('Price', $data['title']);
    }

    public function testCreateColumnWithBoolType(): void
    {
        $type   = Type::bool();
        $column = $this->mapper->createColumn('active', 'Active', $type);

        $this->assertInstanceOf(BooleanColumn::class, $column);
    }

    public function testCreateColumnWithDateTimeType(): void
    {
        $type   = Type::object(\DateTimeImmutable::class);
        $column = $this->mapper->createColumn('createdAt', 'Created At', $type);

        $this->assertInstanceOf(DateColumn::class, $column);
    }
}
