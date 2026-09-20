<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Filter;

use Pentiminax\UX\DataTables\Attribute\DataTableFilter;
use Pentiminax\UX\DataTables\Filter\AttributeFilterReader;
use Pentiminax\UX\DataTables\Filter\CheckboxFilter;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\DateRangeFilter;
use Pentiminax\UX\DataTables\Filter\TernaryFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AttributeFilterReader::class)]
final class AttributeFilterReaderTest extends TestCase
{
    private AttributeFilterReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeFilterReader();
    }

    /**
     * @param class-string $expectedFilterClass
     */
    #[Test]
    #[TestWith([0, TextFilter::class, 'name'])]
    #[TestWith([1, TernaryFilter::class, 'active'])]
    #[TestWith([2, ChoiceFilter::class, 'status'])]
    #[TestWith([3, DateRangeFilter::class, 'createdAt'])]
    public function it_guesses_the_filter_from_the_declared_type(int $index, string $expectedFilterClass, string $expectedName): void
    {
        $filter = $this->reader->readFilters(FilterDataFixture::class)[$index];

        $this->assertInstanceOf($expectedFilterClass, $filter);
        $this->assertSame($expectedName, $filter->getName());
    }

    #[Test]
    public function it_humanizes_the_label_of_a_filter_that_declares_none(): void
    {
        $filter = $this->reader->readFilters(FilterDataFixture::class)[3];

        $this->assertSame('Created At', $filter->jsonSerialize()['label']);
    }

    #[Test]
    public function it_fills_a_choice_filter_from_the_enum_it_reads(): void
    {
        $filter = $this->reader->readFilters(FilterDataFixture::class)[2];

        $this->assertSame(
            ['active' => 'Active', 'inactive' => 'Inactive'],
            $filter->jsonSerialize()['options'],
        );
    }

    #[Test]
    public function it_lets_an_explicit_options_entry_win_over_the_enum(): void
    {
        $filter = $this->reader->readFilters(ExplicitChoicesFixture::class)[0];

        $this->assertSame(['on' => 'Switched on'], $filter->jsonSerialize()['options']);
    }

    #[Test]
    public function it_applies_options_through_the_filter_own_fluent_api(): void
    {
        $filter = $this->reader->readFilters(OptionsFilterFixture::class)[0];
        $data   = $filter->jsonSerialize();

        $this->assertSame('Author name', $data['label']);
        $this->assertSame('Search an author', $data['placeholder']);
    }

    #[Test]
    public function it_spreads_the_two_states_of_a_ternary_filter(): void
    {
        $filter = $this->reader->readFilters(TernaryValuesFixture::class)[0];

        $this->assertInstanceOf(TernaryFilter::class, $filter);
        $this->assertSame('Published', $filter->jsonSerialize()['trueLabel']);
    }

    #[Test]
    public function it_rejects_a_values_option_missing_a_state(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('takes the true value and the false value');

        $this->reader->readFilters(IncompleteValuesFixture::class);
    }

    #[Test]
    public function it_orders_filters_by_position_then_declaration(): void
    {
        $names = array_map(
            static fn ($filter) => $filter->getName(),
            $this->reader->readFilters(PositionedFilterFixture::class),
        );

        $this->assertSame(['first', 'second', 'third'], $names);
    }

    #[Test]
    public function it_reads_a_filter_declared_on_a_getter(): void
    {
        $filter = $this->reader->readFilters(MethodFilterFixture::class)[0];

        $this->assertSame('fullName', $filter->getName());
        $this->assertInstanceOf(TextFilter::class, $filter);
    }

    #[Test]
    public function it_reads_a_filter_declared_on_a_table_class(): void
    {
        $filter = $this->reader->readClassFilters(ClassLevelFilterFixture::class)[0];

        $this->assertSame('archived', $filter->getName());
    }

    #[Test]
    public function it_rejects_a_class_level_filter_without_a_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must carry a name');

        $this->reader->readClassFilters(UnnamedClassFilterFixture::class);
    }

    #[Test]
    public function it_rejects_two_filters_claiming_the_same_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Two filters are declared under the name "state"');

        $this->reader->readFilters(DuplicateFilterFixture::class);
    }

    #[Test]
    public function it_rejects_an_option_no_filter_answers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Option "wobble" is not supported by');

        $this->reader->readFilters(UnknownOptionFilterFixture::class);
    }

    #[Test]
    public function it_rejects_a_type_that_is_not_a_filter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must be a class extending');

        $this->reader->readFilters(NotAFilterFixture::class);
    }

    /**
     * A checkbox filter has no default condition, so one built from an attribute could only throw on
     * the first request that submits it.
     */
    #[Test]
    public function it_rejects_a_checkbox_filter_no_closure_can_reach(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has no default condition');

        $this->reader->readFilters(CheckboxFilterFixture::class);
    }

    /**
     * A parent's private property is recovered, which plain reflection would have dropped. Without an
     * explicit position the child's own declarations come first, since the reader walks up the chain.
     */
    #[Test]
    public function it_reads_a_filter_declared_on_a_parent_private_property(): void
    {
        $names = array_map(
            static fn ($filter) => $filter->getName(),
            $this->reader->readFilters(InheritingFilterFixture::class),
        );

        $this->assertSame(['own', 'reference'], $names);
    }
}

enum FilterStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}

final class FilterDataFixture
{
    #[DataTableFilter]
    public string $name = '';

    #[DataTableFilter]
    public bool $active = true;

    #[DataTableFilter]
    public FilterStatus $status = FilterStatus::Active;

    #[DataTableFilter]
    public \DateTimeImmutable $createdAt;
}

final class ExplicitChoicesFixture
{
    #[DataTableFilter(options: ['options' => ['Switched on' => 'on']])]
    public FilterStatus $status = FilterStatus::Active;
}

final class OptionsFilterFixture
{
    #[DataTableFilter(options: ['label' => 'Author name', 'field' => 'author.name', 'placeholder' => 'Search an author'])]
    public string $author = '';
}

final class TernaryValuesFixture
{
    #[DataTableFilter(options: ['values' => [true, false], 'trueLabel' => 'Published'])]
    public bool $published = true;
}

final class IncompleteValuesFixture
{
    #[DataTableFilter(options: ['values' => [true]])]
    public bool $published = true;
}

final class PositionedFilterFixture
{
    #[DataTableFilter(position: 2)]
    public string $third = '';

    #[DataTableFilter(position: 0)]
    public string $first = '';

    #[DataTableFilter(position: 1)]
    public string $second = '';
}

final class MethodFilterFixture
{
    #[DataTableFilter]
    public function getFullName(): string
    {
        return '';
    }
}

#[DataTableFilter(name: 'archived')]
final class ClassLevelFilterFixture
{
}

#[DataTableFilter(options: ['label' => 'Nameless'])]
final class UnnamedClassFilterFixture
{
}

final class DuplicateFilterFixture
{
    #[DataTableFilter(name: 'state')]
    public string $first = '';

    #[DataTableFilter(name: 'state')]
    public string $second = '';
}

final class UnknownOptionFilterFixture
{
    #[DataTableFilter(options: ['wobble' => true])]
    public string $name = '';
}

final class NotAFilterFixture
{
    #[DataTableFilter(type: \stdClass::class)]
    public string $name = '';
}

final class CheckboxFilterFixture
{
    #[DataTableFilter(type: CheckboxFilter::class)]
    public bool $flagged = true;
}

abstract class FilterSuperclassFixture
{
    #[DataTableFilter]
    private string $reference = '';
}

final class InheritingFilterFixture extends FilterSuperclassFixture
{
    #[DataTableFilter]
    public string $own = '';
}
