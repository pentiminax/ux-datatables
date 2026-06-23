<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Intent;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Intent\ColumnControlIntent;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Intent\InvalidQueryIntentException;
use Pentiminax\UX\DataTables\Query\Intent\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Characterization tests locking the current observable request-to-intent behaviour.
 *
 * @internal
 */
#[CoversClass(DefaultDataTableQueryIntentFactory::class)]
final class DefaultDataTableQueryIntentFactoryTest extends TestCase
{
    #[Test]
    public function it_emits_a_single_order_intent_for_one_valid_order(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);
        $order         = new Order(0, 'desc', 'name');

        $request = new DataTableRequest(1, $columns, order: [$order]);

        $intent = $this->factory()->create($request, [$column]);

        self::assertNotNull($intent->order);
        self::assertSame('name', $intent->order->column->name);
        self::assertSame(SortDirection::Desc, $intent->order->direction);
    }

    #[Test]
    public function it_drops_order_when_multiple_orders_are_requested(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns, order: [
            new Order(0, 'asc', 'name'),
            new Order(0, 'desc', 'name'),
        ]);

        $intent = $this->factory()->create($request, [$column]);

        self::assertNull($intent->order);
    }

    #[Test]
    public function it_drops_order_for_an_unknown_column_index(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns, order: [new Order(5, 'asc', 'name')]);

        $intent = $this->factory()->create($request, [$column]);

        self::assertNull($intent->order);
    }

    #[Test]
    public function it_drops_order_for_a_non_orderable_column(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name')->setOrderable(false);
        $requestColumn = new Column('name', 'name', true, false);
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns, order: [new Order(0, 'asc', 'name')]);

        $intent = $this->factory()->create($request, [$column]);

        self::assertNull($intent->order);
    }

    #[Test]
    public function it_drops_empty_and_whitespace_global_search(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);

        $emptyRequest = new DataTableRequest(1, $columns, search: new Search('', false));
        self::assertNull($this->factory()->create($emptyRequest, [$column])->globalSearch);

        $whitespaceRequest = new DataTableRequest(1, $columns, search: new Search('   ', false));
        self::assertNull($this->factory()->create($whitespaceRequest, [$column])->globalSearch);
    }

    #[Test]
    public function it_emits_global_search_for_a_non_empty_value(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns, search: new Search('john', false));

        $intent = $this->factory()->create($request, [$column]);

        self::assertNotNull($intent->globalSearch);
        self::assertSame('john', $intent->globalSearch->value);
    }

    #[Test]
    public function it_drops_global_search_when_no_column_is_globally_searchable(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name')->disableGlobalSearch();
        $requestColumn = new Column('name', 'name', true, true);
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns, search: new Search('john', false));

        self::assertNull($this->factory()->create($request, [$column])->globalSearch);
    }

    #[Test]
    public function it_drops_empty_and_whitespace_column_searches(): void
    {
        $column      = TextColumn::new('name', 'Name')->setField('name');
        $emptyColumn = new Column('name', 'name', true, true, new Search('', false));
        $columns     = new Columns(['name' => $emptyColumn]);
        $request     = new DataTableRequest(1, $columns);

        self::assertSame([], $this->factory()->create($request, [$column])->columnSearches);

        $whitespaceColumn = new Column('name', 'name', true, true, new Search('   ', false));
        $request          = new DataTableRequest(1, new Columns(['name' => $whitespaceColumn]));

        self::assertSame([], $this->factory()->create($request, [$column])->columnSearches);
    }

    #[Test]
    public function it_skips_non_searchable_columns_for_column_searches(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name')->setSearchable(false);
        $requestColumn = new Column('name', 'name', true, true, new Search('john', false));
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        self::assertSame([], $this->factory()->create($request, [$column])->columnSearches);
    }

    #[Test]
    public function it_emits_a_column_search_intent_for_a_searchable_column(): void
    {
        $column        = TextColumn::new('name', 'Name')->setField('name');
        $requestColumn = new Column('name', 'name', true, true, new Search('john', false));
        $columns       = new Columns(['name' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $intent = $this->factory()->create($request, [$column]);

        self::assertCount(1, $intent->columnSearches);
        self::assertSame('name', $intent->columnSearches[0]->column->name);
        self::assertSame('john', $intent->columnSearches[0]->value);
    }

    #[Test]
    public function it_lets_column_control_list_win_over_scalar_search(): void
    {
        $column = TextColumn::new('status', 'Status')->setField('status');

        $columnControl = new ColumnControl(
            search: new ColumnControlSearch('active', ColumnControlLogic::Contains, 'text'),
            list: ['active', 'pending'],
        );
        $requestColumn = new Column('status', 'status', true, true, columnControl: $columnControl);
        $columns       = new Columns(['status' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $intent = $this->factory()->create($request, [$column]);

        self::assertCount(1, $intent->columnControls);
        $control = $intent->columnControls[0];
        self::assertInstanceOf(ColumnControlIntent::class, $control);
        self::assertTrue($control->isList());
        self::assertSame(ColumnControlLogic::In, $control->logic);
        self::assertSame(['active', 'pending'], $control->values);
        self::assertNull($control->value);
        self::assertSame([], $intent->columnSearches);
    }

    #[Test]
    public function it_emits_a_scalar_column_control_when_no_list_is_present(): void
    {
        $column = TextColumn::new('status', 'Status')->setField('status');

        $columnControl = new ColumnControl(
            search: new ColumnControlSearch('active', ColumnControlLogic::Contains, 'text'),
        );
        $requestColumn = new Column('status', 'status', true, true, columnControl: $columnControl);
        $columns       = new Columns(['status' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $intent = $this->factory()->create($request, [$column]);

        self::assertCount(1, $intent->columnControls);
        $control = $intent->columnControls[0];
        self::assertFalse($control->isList());
        self::assertSame(ColumnControlLogic::Contains, $control->logic);
        self::assertSame('active', $control->value);
        self::assertSame([], $control->values);
    }

    #[Test]
    public function it_keeps_empty_value_for_nullness_column_control(): void
    {
        $column = TextColumn::new('status', 'Status')->setField('status');

        $columnControl = new ColumnControl(
            search: new ColumnControlSearch('', ColumnControlLogic::Empty, 'text'),
        );
        $requestColumn = new Column('status', 'status', true, true, columnControl: $columnControl);
        $columns       = new Columns(['status' => $requestColumn]);

        $request = new DataTableRequest(1, $columns);

        $intent = $this->factory()->create($request, [$column]);

        self::assertCount(1, $intent->columnControls);
        self::assertSame(ColumnControlLogic::Empty, $intent->columnControls[0]->logic);
    }

    #[Test]
    public function it_normalizes_a_non_positive_length_to_no_limit(): void
    {
        $column  = TextColumn::new('name', 'Name')->setField('name');
        $columns = new Columns(['name' => new Column('name', 'name', true, true)]);

        $zeroLength = new DataTableRequest(1, $columns, start: 0, length: 0);
        self::assertNull($this->factory()->create($zeroLength, [$column])->pagination->limit);

        $negativeLength = new DataTableRequest(1, $columns, start: 0, length: -5);
        self::assertNull($this->factory()->create($negativeLength, [$column])->pagination->limit);
    }

    #[Test]
    public function it_keeps_a_positive_length_as_the_limit(): void
    {
        $column  = TextColumn::new('name', 'Name')->setField('name');
        $columns = new Columns(['name' => new Column('name', 'name', true, true)]);

        $request = new DataTableRequest(1, $columns, start: 20, length: 10);

        $pagination = $this->factory()->create($request, [$column])->pagination;

        self::assertSame(20, $pagination->offset);
        self::assertSame(10, $pagination->limit);
    }

    #[Test]
    public function it_normalizes_a_negative_start_to_zero(): void
    {
        $column  = TextColumn::new('name', 'Name')->setField('name');
        $columns = new Columns(['name' => new Column('name', 'name', true, true)]);

        $request = new DataTableRequest(1, $columns, start: -3, length: 10);

        self::assertSame(0, $this->factory()->create($request, [$column])->pagination->offset);
    }

    #[Test]
    public function it_builds_column_read_references_in_display_order(): void
    {
        $name    = TextColumn::new('name', 'Name')->setField('name');
        $id      = NumberColumn::new('id', 'ID')->setField('id');
        $columns = new Columns([]);

        $request = new DataTableRequest(1, $columns);

        $intent = $this->factory()->create($request, [$name, $id]);

        self::assertCount(2, $intent->columns);
        self::assertSame('name', $intent->columns[0]->name);
        self::assertSame('id', $intent->columns[1]->name);
    }

    #[Test]
    public function it_throws_on_duplicate_configured_column_names(): void
    {
        $this->expectException(InvalidQueryIntentException::class);

        $first  = TextColumn::new('name', 'Name')->setField('name');
        $second = TextColumn::new('name', 'Other')->setField('other');

        $request = new DataTableRequest(1, new Columns([]));

        $this->factory()->create($request, [$first, $second]);
    }

    private function factory(): DefaultDataTableQueryIntentFactory
    {
        return new DefaultDataTableQueryIntentFactory();
    }
}
