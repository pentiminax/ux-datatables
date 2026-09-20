<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Attribute\Column;
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The surface introduced by {@see DataTableColumn}; {@see AttributeColumnReaderTest} keeps covering
 * the deprecated {@see Column} spelling.
 *
 * @internal
 */
#[CoversClass(AttributeColumnReader::class)]
final class DataTableColumnReaderTest extends TestCase
{
    private AttributeColumnReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeColumnReader();
    }

    #[Test]
    public function it_applies_open_options_through_the_column_fluent_api(): void
    {
        $column = $this->reader->readColumns(OpenOptionsFixture::class)[0];
        $data   = $column->jsonSerialize();

        $this->assertSame('Full name', $data['title']);
        $this->assertFalse($data['orderable']);
        $this->assertSame('120px', $data['width']);
    }

    /**
     * The eight properties the deprecated attribute had no parameter for are reachable now.
     */
    #[Test]
    public function it_reaches_options_the_deprecated_attribute_could_not_express(): void
    {
        $column = $this->reader->readColumns(UnreachableOptionsFixture::class)[0];

        $this->assertSame('e.provider', $column->getOrderExpression());
        $this->assertSame('p.name', $column->getSearchField());
        $this->assertSame([['join' => 'e.provider', 'alias' => 'p', 'conditionType' => null, 'condition' => null]], $column->getSearchJoins());
        $this->assertSame('ROLE_ADMIN', $column->getPermission());
    }

    #[Test]
    public function it_rejects_an_option_no_column_method_answers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "wobble" is not supported by');

        $this->reader->readColumns(UnknownOptionFixture::class);
    }

    /**
     * The deprecated attribute names every option whatever the column type is, so a date format on
     * a text column has always been ignored rather than rejected.
     */
    #[Test]
    public function it_still_ignores_an_inapplicable_option_on_the_deprecated_attribute(): void
    {
        $column = $this->reader->readColumns(DeprecatedInapplicableOptionFixture::class)[0];

        $this->assertInstanceOf(TextColumn::class, $column);
    }

    #[Test]
    public function it_reads_a_column_declared_on_a_method(): void
    {
        $columns = $this->reader->readColumns(MethodColumnFixture::class);

        $this->assertSame(['fullName', 'active'], array_map(static fn ($column) => $column->getName(), $columns));
        $this->assertSame('Full Name', $columns[0]->jsonSerialize()['title']);
        $this->assertInstanceOf(TextColumn::class, $columns[0]);
        $this->assertInstanceOf(BooleanColumn::class, $columns[1]);
    }

    #[Test]
    public function it_reads_repeated_columns_declared_on_a_table_class(): void
    {
        $columns = $this->reader->readClassColumns(ClassLevelColumnsFixture::class);

        $this->assertSame(['actions', 'createdAt'], array_map(static fn ($column) => $column->getName(), $columns));
        $this->assertInstanceOf(DateColumn::class, $columns[1]);
    }

    #[Test]
    public function it_rejects_a_class_level_column_without_a_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must carry a name');

        $this->reader->readClassColumns(UnnamedClassLevelFixture::class);
    }

    #[Test]
    public function it_orders_members_and_positions_the_same_way_as_before(): void
    {
        $columns = $this->reader->readColumns(OrderedFixture::class);

        $this->assertSame(['first', 'second', 'third'], array_map(static fn ($column) => $column->getName(), $columns));
    }
}

final class OpenOptionsFixture
{
    #[DataTableColumn(options: ['title' => 'Full name', 'orderable' => false, 'width' => '120px'])]
    public string $name = '';
}

final class UnreachableOptionsFixture
{
    #[DataTableColumn(options: [
        'orderExpression' => 'e.provider',
        'searchJoins'     => ['e.provider' => 'p'],
        'searchField'     => 'p.name',
        'permission'      => 'ROLE_ADMIN',
    ])]
    public string $provider = '';
}

final class UnknownOptionFixture
{
    #[DataTableColumn(options: ['wobble' => true])]
    public string $name = '';
}

final class DeprecatedInapplicableOptionFixture
{
    #[Column(format: 'Y-m-d')]
    public string $name = '';
}

final class MethodColumnFixture
{
    #[DataTableColumn]
    public function getFullName(): string
    {
        return '';
    }

    #[DataTableColumn]
    public function isActive(): bool
    {
        return true;
    }
}

#[DataTableColumn(name: 'actions', options: ['orderable' => false])]
#[DataTableColumn(DateColumn::class, name: 'createdAt')]
final class ClassLevelColumnsFixture
{
}

#[DataTableColumn(options: ['title' => 'Nameless'])]
final class UnnamedClassLevelFixture
{
}

final class OrderedFixture
{
    #[DataTableColumn(position: 2)]
    public string $third = '';

    #[DataTableColumn(position: 0)]
    public string $first = '';

    #[DataTableColumn(position: 1)]
    public string $second = '';
}
