<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Intent;

use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControl;
use Pentiminax\UX\DataTables\DataTableRequest\ColumnControlSearch;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\DataTableRequest\Search;
use Pentiminax\UX\DataTables\Enum\ColumnControlLogic;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Query\Intent\InvalidQueryIntentException;
use Pentiminax\UX\DataTables\Query\Intent\SortDirection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
    /**
     * @return iterable<string, array{ColumnInterface, list<Order>, ?SortDirection}>
     */
    public static function orderRequests(): iterable
    {
        yield 'single valid order' => [
            TextColumn::new('name', 'Name')->setField('name'),
            [new Order(0, 'desc', 'name')],
            SortDirection::Desc,
        ];

        yield 'multiple orders' => [
            TextColumn::new('name', 'Name')->setField('name'),
            [new Order(0, 'asc', 'name'), new Order(0, 'desc', 'name')],
            null,
        ];

        yield 'unknown column name' => [
            TextColumn::new('name', 'Name')->setField('name'),
            [new Order(5, 'asc', 'column_5')],
            null,
        ];

        yield 'order index shifted by a leading client column' => [
            TextColumn::new('name', 'Name')->setField('name'),
            [new Order(1, 'desc', 'name')],
            SortDirection::Desc,
        ];

        yield 'non orderable column' => [
            TextColumn::new('name', 'Name')->setField('name')->setOrderable(false),
            [new Order(0, 'asc', 'name')],
            null,
        ];
    }

    /**
     * @return iterable<string, array{ColumnInterface, string, ?string}>
     */
    public static function globalSearchRequests(): iterable
    {
        yield 'empty value' => [TextColumn::new('name', 'Name')->setField('name'), '', null];

        yield 'whitespace value' => [TextColumn::new('name', 'Name')->setField('name'), '   ', null];

        yield 'non empty value' => [TextColumn::new('name', 'Name')->setField('name'), 'john', 'john'];

        yield 'no globally searchable column' => [
            TextColumn::new('name', 'Name')->setField('name')->disableGlobalSearch(),
            'john',
            null,
        ];
    }

    /**
     * @return iterable<string, array{ColumnInterface, string, ?string}>
     */
    public static function columnSearchRequests(): iterable
    {
        yield 'empty value' => [TextColumn::new('name', 'Name')->setField('name'), '', null];

        yield 'whitespace value' => [TextColumn::new('name', 'Name')->setField('name'), '   ', null];

        yield 'non searchable column' => [
            TextColumn::new('name', 'Name')->setField('name')->setSearchable(false),
            'john',
            null,
        ];

        yield 'searchable column' => [TextColumn::new('name', 'Name')->setField('name'), 'john', 'john'];
    }

    /**
     * @return iterable<string, array{ColumnControl, ColumnControlLogic, bool, ?string, list<string>}>
     */
    public static function columnControlRequests(): iterable
    {
        yield 'list wins over scalar search' => [
            new ColumnControl(
                search: new ColumnControlSearch('active', ColumnControlLogic::Contains, 'text'),
                list: ['active', 'pending'],
            ),
            ColumnControlLogic::In,
            true,
            null,
            ['active', 'pending'],
        ];

        yield 'scalar search without list' => [
            new ColumnControl(search: new ColumnControlSearch('active', ColumnControlLogic::Contains, 'text')),
            ColumnControlLogic::Contains,
            false,
            'active',
            [],
        ];

        yield 'nullness logic keeps the empty value' => [
            new ColumnControl(search: new ColumnControlSearch('', ColumnControlLogic::Empty, 'text')),
            ColumnControlLogic::Empty,
            false,
            '',
            [],
        ];
    }

    /**
     * @return iterable<string, array{int, int, int, ?int}>
     */
    public static function paginationRequests(): iterable
    {
        yield 'zero length' => [0, 0, 0, null];

        yield 'negative length' => [0, -5, 0, null];

        yield 'positive length' => [20, 10, 20, 10];

        yield 'negative start' => [-3, 10, 0, 10];
    }

    /**
     * @param list<Order> $order
     */
    #[Test]
    #[DataProvider('orderRequests')]
    public function it_builds_the_order_intent(ColumnInterface $column, array $order, ?SortDirection $expectedDirection): void
    {
        $request = new DataTableRequest(1, $this->requestColumns($column), order: $order);

        $intent = $this->intent($request, $column);

        if (null === $expectedDirection) {
            self::assertNull($intent->order);

            return;
        }

        self::assertNotNull($intent->order);
        self::assertSame($column->getName(), $intent->order->column->name);
        self::assertSame($expectedDirection, $intent->order->direction);
    }

    #[Test]
    #[DataProvider('globalSearchRequests')]
    public function it_builds_the_global_search_intent(ColumnInterface $column, string $value, ?string $expectedValue): void
    {
        $request = new DataTableRequest(1, $this->requestColumns($column), search: new Search($value, false));

        $globalSearch = $this->intent($request, $column)->globalSearch;

        if (null === $expectedValue) {
            self::assertNull($globalSearch);

            return;
        }

        self::assertNotNull($globalSearch);
        self::assertSame($expectedValue, $globalSearch->value);
    }

    #[Test]
    #[DataProvider('columnSearchRequests')]
    public function it_builds_the_column_search_intents(ColumnInterface $column, string $value, ?string $expectedValue): void
    {
        $request = new DataTableRequest(1, $this->requestColumns($column, new Search($value, false)));

        $columnSearches = $this->intent($request, $column)->columnSearches;

        if (null === $expectedValue) {
            self::assertSame([], $columnSearches);

            return;
        }

        self::assertCount(1, $columnSearches);
        self::assertSame($column->getName(), $columnSearches[0]->column->name);
        self::assertSame($expectedValue, $columnSearches[0]->value);
    }

    /**
     * @param list<string> $expectedValues
     */
    #[Test]
    #[DataProvider('columnControlRequests')]
    public function it_builds_the_column_control_intent(
        ColumnControl $columnControl,
        ColumnControlLogic $expectedLogic,
        bool $expectedIsList,
        ?string $expectedValue,
        array $expectedValues,
    ): void {
        $column  = TextColumn::new('status', 'Status')->setField('status');
        $request = new DataTableRequest(1, $this->requestColumns($column, columnControl: $columnControl));

        $intent = $this->intent($request, $column);

        self::assertCount(1, $intent->columnControls);

        $control = $intent->columnControls[0];
        self::assertSame($expectedLogic, $control->logic);
        self::assertSame($expectedIsList, $control->isList());
        self::assertSame($expectedValue, $control->value);
        self::assertSame($expectedValues, $control->values);
        self::assertSame([], $intent->columnSearches);
    }

    #[Test]
    #[DataProvider('paginationRequests')]
    public function it_normalizes_pagination(int $start, int $length, int $expectedOffset, ?int $expectedLimit): void
    {
        $column  = TextColumn::new('name', 'Name')->setField('name');
        $request = new DataTableRequest(1, $this->requestColumns($column), start: $start, length: $length);

        $pagination = $this->intent($request, $column)->pagination;

        self::assertSame($expectedOffset, $pagination->offset);
        self::assertSame($expectedLimit, $pagination->limit);
    }

    #[Test]
    public function it_builds_column_read_references_in_display_order(): void
    {
        $name = TextColumn::new('name', 'Name')->setField('name');
        $id   = NumberColumn::new('id', 'ID')->setField('id');

        $request = new DataTableRequest(1, new Columns([]));

        $intent = $this->intent($request, $name, $id);

        self::assertCount(2, $intent->columns);
        self::assertSame('name', $intent->columns[0]->name);
        self::assertSame('id', $intent->columns[1]->name);
    }

    #[Test]
    public function it_maps_shifted_request_columns_by_name(): void
    {
        $name  = TextColumn::new('name', 'Name')->setField('name');
        $email = TextColumn::new('email', 'Email')->setField('email');

        $request = new DataTableRequest(
            draw: 1,
            columns: new Columns([
                ''      => new Column('', '', false, false),
                'name'  => new Column('name', 'name', true, true, new Search('alice', false)),
                'email' => new Column(
                    'email',
                    'email',
                    true,
                    true,
                    columnControl: new ColumnControl(search: new ColumnControlSearch('acme', ColumnControlLogic::Contains, 'text')),
                ),
            ]),
            order: [new Order(2, 'asc', 'email')],
        );

        $intent = $this->intent($request, $name, $email);

        self::assertNotNull($intent->order);
        self::assertSame('email', $intent->order->column->name);
        self::assertSame(SortDirection::Asc, $intent->order->direction);

        self::assertCount(1, $intent->columnSearches);
        self::assertSame('name', $intent->columnSearches[0]->column->name);
        self::assertSame('alice', $intent->columnSearches[0]->value);

        self::assertCount(1, $intent->columnControls);
        self::assertSame('email', $intent->columnControls[0]->column->name);
        self::assertSame(ColumnControlLogic::Contains, $intent->columnControls[0]->logic);
        self::assertSame('acme', $intent->columnControls[0]->value);
    }

    #[Test]
    public function it_throws_on_duplicate_configured_column_names(): void
    {
        $this->expectException(InvalidQueryIntentException::class);

        $first  = TextColumn::new('name', 'Name')->setField('name');
        $second = TextColumn::new('name', 'Other')->setField('other');

        $this->intent(new DataTableRequest(1, new Columns([])), $first, $second);
    }

    private function intent(DataTableRequest $request, ColumnInterface ...$columns): DataTableQueryIntent
    {
        return (new DefaultDataTableQueryIntentFactory())->create($request, $columns);
    }

    /**
     * Request payload for a single configured column, always searchable and orderable so
     * only the configured column drives what the intent keeps.
     */
    private function requestColumns(
        ColumnInterface $column,
        ?Search $search = null,
        ?ColumnControl $columnControl = null,
    ): Columns {
        $name = $column->getName();

        return new Columns([$name => new Column($name, $name, true, true, $search, $columnControl)]);
    }
}
