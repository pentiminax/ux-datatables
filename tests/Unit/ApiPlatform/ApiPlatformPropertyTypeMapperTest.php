<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\ApiPlatform;

use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\TypeInfo\Type;

/**
 * @internal
 */
#[CoversClass(ApiPlatformPropertyTypeMapper::class)]
final class ApiPlatformPropertyTypeMapperTest extends TestCase
{
    /**
     * @param class-string $expectedColumnClass
     */
    #[Test]
    #[DataProvider('provideTypeMappings')]
    public function it_maps_a_type_to_a_column(?Type $type, string $expectedColumnClass, string $expectedType): void
    {
        $mapper = new ApiPlatformPropertyTypeMapper();

        $this->assertSame($expectedColumnClass, $mapper->mapType($type));

        $column = $mapper->createColumn('price', 'Price', $type);

        $this->assertInstanceOf($expectedColumnClass, $column);
        $this->assertSame([
            'data'       => 'price',
            'name'       => 'price',
            'orderable'  => true,
            'searchable' => true,
            'title'      => 'Price',
            'type'       => $expectedType,
            'visible'    => true,
            'field'      => 'price',
        ], $column->jsonSerialize());
    }

    /**
     * @return iterable<string, array{0: ?Type, 1: class-string, 2: string}>
     */
    public static function provideTypeMappings(): iterable
    {
        yield 'bool' => [Type::bool(), BooleanColumn::class, 'num'];
        yield 'int' => [Type::int(), NumberColumn::class, 'num'];
        yield 'float' => [Type::float(), NumberColumn::class, 'num'];
        yield 'string' => [Type::string(), TextColumn::class, 'string'];
        yield 'DateTime' => [Type::object(\DateTime::class), DateColumn::class, 'date'];
        yield 'DateTimeImmutable' => [Type::object(\DateTimeImmutable::class), DateColumn::class, 'date'];
        yield 'object-not-date' => [Type::object(\stdClass::class), TextColumn::class, 'string'];
        yield 'array' => [Type::array(), TextColumn::class, 'string'];
        yield 'null' => [null, TextColumn::class, 'string'];
    }
}
