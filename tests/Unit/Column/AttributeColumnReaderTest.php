<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\ChoiceColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttributeColumnReader::class)]
final class AttributeColumnReaderTest extends TestCase
{
    private AttributeColumnReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeColumnReader();
    }

    /**
     * @param class-string $entityClass
     * @param list<string> $expectedNames
     */
    #[Test]
    #[TestWith([ReaderEntityFixture::class, ['id', 'firstName', 'active', 'createdAt']])]
    #[TestWith([MixedAnnotationFixture::class, ['name']])]
    #[TestWith([NoAttributeFixture::class, []])]
    #[TestWith([PositionFixture::class, ['first', 'second', 'third']])]
    #[TestWith([StableSortFixture::class, ['alpha', 'beta', 'gamma']])]
    public function it_reads_annotated_properties_in_position_order(string $entityClass, array $expectedNames): void
    {
        $columns = $this->reader->readColumns($entityClass);

        $this->assertSame($expectedNames, array_map(static fn ($column) => $column->getName(), $columns));
    }

    /**
     * @param class-string $expectedColumnClass
     */
    #[Test]
    #[TestWith([0, NumberColumn::class, 'id', 'ID'])]
    #[TestWith([1, TextColumn::class, 'firstName', 'First Name'])]
    #[TestWith([2, BooleanColumn::class, 'active', 'Active'])]
    #[TestWith([3, DateColumn::class, 'createdAt', 'Created At'])]
    public function it_infers_column_type_and_humanized_label(int $index, string $expectedColumnClass, string $expectedName, string $expectedTitle): void
    {
        $column = $this->reader->readColumns(ReaderEntityFixture::class)[$index];

        $this->assertInstanceOf($expectedColumnClass, $column);
        $this->assertSame($expectedName, $column->getName());
        $this->assertSame($expectedTitle, $column->jsonSerialize()['title']);
    }

    /**
     * @param class-string $entityClass
     * @param class-string $expectedColumnClass
     */
    #[Test]
    #[TestWith([ExplicitTypeFixture::class, TextColumn::class, 'code', 'Code'])]
    #[TestWith([CustomNameFixture::class, TextColumn::class, 'full_name', 'First Name'])]
    #[TestWith([ExplicitLabelFixture::class, TextColumn::class, 'firstName', 'Full Name'])]
    public function it_applies_explicit_attribute_overrides(string $entityClass, string $expectedColumnClass, string $expectedName, string $expectedTitle): void
    {
        $columns = $this->reader->readColumns($entityClass);

        $this->assertCount(1, $columns);
        $this->assertInstanceOf($expectedColumnClass, $columns[0]);
        $this->assertSame($expectedName, $columns[0]->getName());
        $this->assertSame($expectedTitle, $columns[0]->jsonSerialize()['title']);
    }

    #[Test]
    public function it_applies_format_to_date_column(): void
    {
        $dateColumn = $this->reader->readColumns(ReaderEntityFixture::class)[3];

        $this->assertInstanceOf(DateColumn::class, $dateColumn);
        $this->assertSame('Y-m-d', $dateColumn->getFormat());
    }

    #[Test]
    public function it_reads_column_options(): void
    {
        $columns = $this->reader->readColumns(OptionsFixture::class);

        $column = $columns[0];
        $data   = $column->jsonSerialize();

        $this->assertFalse($data['orderable']);
        $this->assertFalse($data['searchable']);
        $this->assertFalse($data['visible']);
        $this->assertSame('120px', $data['width']);
        $this->assertSame(2, $data['responsivePriority']);
        $this->assertSame('text-center', $data['className']);
        $this->assertSame('th', $data['cellType']);
        $this->assertArrayNotHasKey('render', $data);
        $this->assertSame('N/A', $data['defaultContent']);
        $this->assertFalse($column->isExportable());
        $this->assertFalse($column->isGlobalSearchable());
    }

    #[Test]
    public function it_reads_field_option(): void
    {
        $columns = $this->reader->readColumns(FieldFixture::class);

        $this->assertSame('author.name', $columns[0]->getField());
    }

    #[Test]
    public function it_reads_choice_column_badge_options(): void
    {
        $columns = $this->reader->readColumns(ChoiceOptionsFixture::class);

        $this->assertCount(1, $columns);
        $this->assertInstanceOf(ChoiceColumn::class, $columns[0]);

        $customOptions = $columns[0]->jsonSerialize()['customOptions'];

        $this->assertSame(['active' => 'success', 'inactive' => 'danger'], $customOptions['renderAsBadges']);
        $this->assertSame('secondary', $customOptions['defaultBadgeVariant']);
    }

    #[Test]
    public function it_reads_columns_declared_by_a_parent_class(): void
    {
        $columns = $this->reader->readColumns(InheritedColumnsFixture::class);

        $this->assertSame(['id', 'label', 'reference'], array_map(static fn ($column) => $column->getName(), $columns));
    }

    #[Test]
    public function it_lets_a_child_redeclaration_win_over_its_parent(): void
    {
        $columns = $this->reader->readColumns(RedeclaringChildFixture::class);

        $this->assertCount(1, $columns);
        $this->assertSame('Child', $columns[0]->jsonSerialize()['title']);
    }
}

final class ReaderEntityFixture
{
    #[DataTableColumn]
    public int $id = 0;

    #[DataTableColumn]
    public string $firstName = '';

    #[DataTableColumn]
    public bool $active = true;

    #[DataTableColumn(options: ['format' => 'Y-m-d'])]
    public \DateTimeImmutable $createdAt;
}

final class ExplicitTypeFixture
{
    #[DataTableColumn(type: TextColumn::class)]
    public int $code = 0;
}

final class CustomNameFixture
{
    #[DataTableColumn(name: 'full_name')]
    public string $firstName = '';
}

final class ExplicitLabelFixture
{
    #[DataTableColumn(options: ['title' => 'Full Name'])]
    public string $firstName = '';
}

final class MixedAnnotationFixture
{
    #[DataTableColumn]
    public string $name = '';

    public string $secret = '';
}

final class PositionFixture
{
    #[DataTableColumn(position: 2)]
    public string $third = '';

    #[DataTableColumn(position: 0)]
    public string $first = '';

    #[DataTableColumn(position: 1)]
    public string $second = '';
}

final class StableSortFixture
{
    #[DataTableColumn]
    public string $alpha = '';

    #[DataTableColumn]
    public string $beta = '';

    #[DataTableColumn]
    public string $gamma = '';
}

final class OptionsFixture
{
    #[DataTableColumn(options: [
        'orderable'          => false,
        'searchable'         => false,
        'visible'            => false,
        'exportable'         => false,
        'globalSearchable'   => false,
        'width'              => '120px',
        'responsivePriority' => 2,
        'className'          => 'text-center',
        'cellType'           => 'th',
        'defaultContent'     => 'N/A',
    ])]
    public string $value = '';
}

final class FieldFixture
{
    #[DataTableColumn(options: ['field' => 'author.name'])]
    public string $authorName = '';
}

final class ChoiceOptionsFixture
{
    #[DataTableColumn(type: ChoiceColumn::class, options: ['renderAsBadges' => ['active' => 'success', 'inactive' => 'danger']])]
    public string $status = '';
}

final class NoAttributeFixture
{
    public string $name = '';
    public int $age     = 0;
}

abstract class MappedSuperclassFixture
{
    #[DataTableColumn(position: 0)]
    private int $id = 0;

    #[DataTableColumn(position: 1)]
    protected string $label = '';
}

final class InheritedColumnsFixture extends MappedSuperclassFixture
{
    #[DataTableColumn(position: 2)]
    public string $reference = '';
}

abstract class RedeclaringParentFixture
{
    #[DataTableColumn(options: ['title' => 'Parent'])]
    protected string $label = '';
}

final class RedeclaringChildFixture extends RedeclaringParentFixture
{
    #[DataTableColumn(options: ['title' => 'Child'])]
    protected string $label = '';
}
