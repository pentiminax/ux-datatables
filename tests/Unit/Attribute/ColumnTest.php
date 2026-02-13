<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Attribute;

use Pentiminax\UX\DataTables\Attribute\Column;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $attr = new Column();

        $this->assertNull($attr->type);
        $this->assertNull($attr->name);
        $this->assertNull($attr->title);
        $this->assertTrue($attr->orderable);
        $this->assertTrue($attr->searchable);
        $this->assertTrue($attr->visible);
        $this->assertTrue($attr->exportable);
        $this->assertTrue($attr->globalSearchable);
        $this->assertNull($attr->width);
        $this->assertNull($attr->className);
        $this->assertNull($attr->cellType);
        $this->assertNull($attr->render);
        $this->assertNull($attr->defaultContent);
        $this->assertNull($attr->field);
        $this->assertNull($attr->format);
        $this->assertSame(0, $attr->priority);
    }

    public function testExplicitValues(): void
    {
        $attr = new Column(
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
            render: 'renderPrice',
            defaultContent: 'N/A',
            field: 'product.price',
            format: 'Y-m-d',
            priority: 10,
        );

        $this->assertSame(NumberColumn::class, $attr->type);
        $this->assertSame('price', $attr->name);
        $this->assertSame('Product Price', $attr->title);
        $this->assertFalse($attr->orderable);
        $this->assertFalse($attr->searchable);
        $this->assertFalse($attr->visible);
        $this->assertFalse($attr->exportable);
        $this->assertFalse($attr->globalSearchable);
        $this->assertSame('120px', $attr->width);
        $this->assertSame('text-right', $attr->className);
        $this->assertSame('th', $attr->cellType);
        $this->assertSame('renderPrice', $attr->render);
        $this->assertSame('N/A', $attr->defaultContent);
        $this->assertSame('product.price', $attr->field);
        $this->assertSame('Y-m-d', $attr->format);
        $this->assertSame(10, $attr->priority);
    }

    public function testReadViaReflection(): void
    {
        $reflection = new \ReflectionClass(ColumnAttributeFixture::class);
        $property   = $reflection->getProperty('title');
        $attributes = $property->getAttributes(Column::class);

        $this->assertCount(1, $attributes);

        $instance = $attributes[0]->newInstance();
        $this->assertInstanceOf(Column::class, $instance);
        $this->assertSame('Title', $instance->title);
    }
}

final class ColumnAttributeFixture
{
    #[Column(title: 'Title')]
    public string $title = '';
}
