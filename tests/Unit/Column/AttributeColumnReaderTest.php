<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Attribute\Column;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\TestCase;

class AttributeColumnReaderTest extends TestCase
{
    private AttributeColumnReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeColumnReader();
    }

    public function testReadsAnnotatedProperties(): void
    {
        $columns = $this->reader->readColumns(ReaderEntityFixture::class);

        $this->assertCount(4, $columns);
    }

    public function testInfersCorrectColumnTypes(): void
    {
        $columns = $this->reader->readColumns(ReaderEntityFixture::class);

        $this->assertInstanceOf(NumberColumn::class, $columns[0]);
        $this->assertInstanceOf(TextColumn::class, $columns[1]);
        $this->assertInstanceOf(BooleanColumn::class, $columns[2]);
        $this->assertInstanceOf(DateColumn::class, $columns[3]);
    }

    public function testExplicitTypeOverridesInference(): void
    {
        $columns = $this->reader->readColumns(ExplicitTypeFixture::class);

        $this->assertCount(1, $columns);
        $this->assertInstanceOf(TextColumn::class, $columns[0]);
    }

    public function testDefaultNameIsPropertyName(): void
    {
        $columns = $this->reader->readColumns(ReaderEntityFixture::class);

        $this->assertSame('id', $columns[0]->getName());
        $this->assertSame('firstName', $columns[1]->getName());
    }

    public function testExplicitNameOverridesDefault(): void
    {
        $columns = $this->reader->readColumns(CustomNameFixture::class);

        $this->assertSame('full_name', $columns[0]->getName());
    }

    public function testHumanizedLabels(): void
    {
        $columns = $this->reader->readColumns(ReaderEntityFixture::class);

        $data = $columns[0]->jsonSerialize();
        $this->assertSame('ID', $data['title']);

        $data = $columns[1]->jsonSerialize();
        $this->assertSame('First Name', $data['title']);
    }

    public function testExplicitLabel(): void
    {
        $columns = $this->reader->readColumns(ExplicitLabelFixture::class);

        $data = $columns[0]->jsonSerialize();
        $this->assertSame('Full Name', $data['title']);
    }

    public function testFormatAppliedToDateColumn(): void
    {
        $columns = $this->reader->readColumns(ReaderEntityFixture::class);

        $dateColumn = $columns[3];
        $this->assertInstanceOf(DateColumn::class, $dateColumn);
        $this->assertSame('Y-m-d', $dateColumn->getFormat());
    }

    public function testUnannotatedPropertiesIgnored(): void
    {
        $columns = $this->reader->readColumns(MixedAnnotationFixture::class);

        $this->assertCount(1, $columns);
        $this->assertSame('name', $columns[0]->getName());
    }

    public function testSortedByPriority(): void
    {
        $columns = $this->reader->readColumns(PriorityFixture::class);

        $this->assertSame('first', $columns[0]->getName());
        $this->assertSame('second', $columns[1]->getName());
        $this->assertSame('third', $columns[2]->getName());
    }

    public function testColumnOptions(): void
    {
        $columns = $this->reader->readColumns(OptionsFixture::class);

        $column = $columns[0];
        $data   = $column->jsonSerialize();

        $this->assertFalse($data['orderable']);
        $this->assertFalse($data['searchable']);
        $this->assertFalse($data['visible']);
        $this->assertSame('120px', $data['width']);
        $this->assertSame('text-center not-exportable', $data['className']);
        $this->assertSame('th', $data['cellType']);
        $this->assertSame('renderFn', $data['render']);
        $this->assertSame('N/A', $data['defaultContent']);
        $this->assertFalse($column->isExportable());
        $this->assertFalse($column->isGlobalSearchable());
    }

    public function testFieldOption(): void
    {
        $columns = $this->reader->readColumns(FieldFixture::class);

        $this->assertSame('author.name', $columns[0]->getField());
    }

    public function testEntityWithNoAttributesReturnsEmpty(): void
    {
        $columns = $this->reader->readColumns(NoAttributeFixture::class);

        $this->assertSame([], $columns);
    }
}

final class ReaderEntityFixture
{
    #[Column]
    public int $id = 0;

    #[Column]
    public string $firstName = '';

    #[Column]
    public bool $active = true;

    #[Column(format: 'Y-m-d')]
    public \DateTimeImmutable $createdAt;
}

final class ExplicitTypeFixture
{
    #[Column(type: TextColumn::class)]
    public int $code = 0;
}

final class CustomNameFixture
{
    #[Column(name: 'full_name')]
    public string $firstName = '';
}

final class ExplicitLabelFixture
{
    #[Column(title: 'Full Name')]
    public string $firstName = '';
}

final class MixedAnnotationFixture
{
    #[Column]
    public string $name = '';

    public string $secret = '';
}

final class PriorityFixture
{
    #[Column(priority: 2)]
    public string $third = '';

    #[Column(priority: 0)]
    public string $first = '';

    #[Column(priority: 1)]
    public string $second = '';
}

final class OptionsFixture
{
    #[Column(
        orderable: false,
        searchable: false,
        visible: false,
        exportable: false,
        globalSearchable: false,
        width: '120px',
        className: 'text-center',
        cellType: 'th',
        render: 'renderFn',
        defaultContent: 'N/A',
    )]
    public string $value = '';
}

final class FieldFixture
{
    #[Column(field: 'author.name')]
    public string $authorName = '';
}

final class NoAttributeFixture
{
    public string $name = '';
    public int $age     = 0;
}
