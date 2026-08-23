<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Pentiminax\UX\DataTables\Attribute\Column;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Column::class)]
final class ColumnTest extends TestCase
{
    /**
     * @param array<string, mixed> $expected
     */
    #[Test]
    #[DataProvider('provideColumnAttributes')]
    public function it_exposes_the_configured_metadata(Column $attribute, array $expected): void
    {
        foreach ($expected as $property => $value) {
            $this->assertSame($value, $attribute->{$property}, $property);
        }
    }

    /**
     * @return iterable<string, array{Column, array<string, mixed>}>
     */
    public static function provideColumnAttributes(): iterable
    {
        yield 'defaults' => [
            new Column(),
            [
                'type'             => null,
                'name'             => null,
                'title'            => null,
                'orderable'        => true,
                'searchable'       => true,
                'visible'          => true,
                'exportable'       => true,
                'globalSearchable' => true,
                'width'            => null,
                'className'        => null,
                'cellType'         => null,
                'defaultContent'   => null,
                'field'            => null,
                'format'           => null,
                'position'         => null,
            ],
        ];

        yield 'explicit values' => [
            new Column(
                type: NumberColumn::class,
                name: 'price',
                title: 'Product Price',
                orderable: false,
                searchable: false,
                visible: false,
                exportable: false,
                globalSearchable: false,
                width: '120px',
                className: 'text-right',
                cellType: 'th',
                defaultContent: 'N/A',
                field: 'product.price',
                format: 'Y-m-d',
                position: 10,
            ),
            [
                'type'             => NumberColumn::class,
                'name'             => 'price',
                'title'            => 'Product Price',
                'orderable'        => false,
                'searchable'       => false,
                'visible'          => false,
                'exportable'       => false,
                'globalSearchable' => false,
                'width'            => '120px',
                'className'        => 'text-right',
                'cellType'         => 'th',
                'defaultContent'   => 'N/A',
                'field'            => 'product.price',
                'format'           => 'Y-m-d',
                'position'         => 10,
            ],
        ];
    }

    #[Test]
    public function it_can_be_read_via_reflection(): void
    {
        $attributes = (new \ReflectionClass(ColumnAttributeFixture::class))
            ->getProperty('title')
            ->getAttributes(Column::class);

        $this->assertCount(1, $attributes);
        $this->assertSame('Title', $attributes[0]->newInstance()->title);
    }
}

final class ColumnAttributeFixture
{
    #[Column(title: 'Title')]
    public string $title = '';
}
