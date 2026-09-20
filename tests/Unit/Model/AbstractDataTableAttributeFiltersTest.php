<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Attribute\DataTableFilter;
use Pentiminax\UX\DataTables\Filter\ChoiceFilter;
use Pentiminax\UX\DataTables\Filter\TextFilter;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Filters;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(AbstractDataTable::class)]
final class AbstractDataTableAttributeFiltersTest extends TestCase
{
    #[Test]
    public function it_reads_the_filters_declared_on_the_data_class(): void
    {
        $filters = $this->filtersOf(new AttributeFilteredTable());

        $this->assertSame(['name', 'state'], array_map(
            static fn ($filter) => $filter->getName(),
            $filters->getFilters(),
        ));
        $this->assertInstanceOf(TextFilter::class, $filters->get('name'));
        $this->assertInstanceOf(ChoiceFilter::class, $filters->get('state'));
    }

    #[Test]
    public function it_leaves_the_data_class_alone_when_the_table_class_declares_filters(): void
    {
        $filters = $this->filtersOf(new ShadowingFilterTable());

        $this->assertSame(['archived'], array_map(
            static fn ($filter) => $filter->getName(),
            $filters->getFilters(),
        ));
    }

    #[Test]
    public function it_keeps_the_filters_configure_filters_declared(): void
    {
        $filters = $this->filtersOf(new FluentFilterTable());

        $this->assertSame(['manual'], array_map(
            static fn ($filter) => $filter->getName(),
            $filters->getFilters(),
        ));
    }

    #[Test]
    public function it_declares_no_filter_without_an_attribute(): void
    {
        $this->assertTrue($this->filtersOf(new BareFilterTable())->isEmpty());
    }

    /**
     * getConfiguredDataTable() stops at initialization, where the filters are already resolved. Going
     * as far as rendering would pull in a data provider none of these fixtures needs.
     */
    private function filtersOf(AbstractDataTable $table): Filters
    {
        $table->setDataTableInfrastructure(DataTableInfrastructure::createDefault());

        return $table->getConfiguredDataTable()->getFilters();
    }
}

enum TableFilterState: string
{
    case Open   = 'open';
    case Closed = 'closed';
}

final class FilteredDataFixture
{
    #[DataTableFilter]
    public string $name = '';

    #[DataTableFilter(name: 'state')]
    public TableFilterState $status = TableFilterState::Open;
}

#[AsDataTable(dataClass: FilteredDataFixture::class)]
final class AttributeFilteredTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}

#[AsDataTable(dataClass: FilteredDataFixture::class)]
#[DataTableFilter(name: 'archived')]
final class ShadowingFilterTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}

#[AsDataTable(dataClass: FilteredDataFixture::class)]
final class FluentFilterTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(TextFilter::new('manual'));
    }
}

final class BareFilterTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [];
    }
}
