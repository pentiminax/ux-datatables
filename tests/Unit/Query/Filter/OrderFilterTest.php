<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Query\Filter;

use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\DataTableRequest\Column;
use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use Pentiminax\UX\DataTables\Query\DoctrineSortDirection;
use Pentiminax\UX\DataTables\Query\Filter\OrderFilter;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntent;
use Pentiminax\UX\DataTables\Query\QueryFilterContext;
use Pentiminax\UX\DataTables\Tests\Support\BuildsQueryFilterContext;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OrderFilter::class)]
final class OrderFilterTest extends TestCase
{
    use BuildsQueryFilterContext;

    /**
     * @return iterable<string, array{ColumnInterface, string, string, ?array{string, string}}>
     */
    public static function appliedOrders(): iterable
    {
        yield 'simple field' => [
            TextColumn::new('name', 'Name')->setField('name'),
            'asc',
            'e.name',
            null,
        ];

        yield 'configured field wins over the request column name' => [
            TextColumn::new('displayName', 'Display Name')->setField('customField'),
            'desc',
            'e.customField',
            null,
        ];

        yield 'order expression used verbatim, without alias prefix or join resolution' => [
            TextColumn::new('invoiceCount', 'Invoices')->setOrderExpression('invoiceCount'),
            'desc',
            'invoiceCount',
            null,
        ];

        yield 'dot notation field' => [
            TextColumn::new('authorName', 'Author')->setField('author.firstName'),
            'asc',
            'author.firstName',
            ['e.author', 'author'],
        ];
    }

    /**
     * @return iterable<string, array{list<Order>, list<ColumnInterface>}>
     */
    public static function skippedOrders(): iterable
    {
        yield 'no order requested' => [[], [TextColumn::new('name', 'Name')->setField('name')]];

        yield 'order column index out of bounds' => [[new Order(5, 'asc', 'name')], []];
    }

    /**
     * @param ?array{string, string} $expectedJoin
     */
    #[Test]
    #[DataProvider('appliedOrders')]
    public function it_applies_order(
        ColumnInterface $column,
        string $direction,
        string $expectedExpression,
        ?array $expectedJoin,
    ): void {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        if (null === $expectedJoin) {
            $qb->expects($this->never())->method('leftJoin');
        } else {
            $qb->expects($this->once())
                ->method('leftJoin')
                ->with($expectedJoin[0], $expectedJoin[1])
                ->willReturn($qb);
        }

        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with($expectedExpression, DoctrineSortDirection::from($direction));

        (new OrderFilter())->apply($qb, $this->orderedContext($column, $direction));
    }

    /**
     * @param list<Order>           $order
     * @param list<ColumnInterface> $columns
     */
    #[Test]
    #[DataProvider('skippedOrders')]
    public function it_skips_ordering(array $order, array $columns): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects($this->never())->method('addOrderBy');

        $requestColumns = new Columns(['name' => new Column('name', 'name', true, true)]);
        $request        = new DataTableRequest(1, $requestColumns, order: $order);

        (new OrderFilter())->apply($qb, $this->context($request, $columns));
    }

    #[Test]
    public function it_applies_every_order_criterion_in_request_order(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);

        $calls = [];
        $qb->expects($this->exactly(3))->method('addOrderBy')->willReturnCallback(function (string $sort, mixed $order) use (&$calls, $qb): QueryBuilder {
            $calls[] = [$sort, $order];

            return $qb;
        });

        $columns = [
            TextColumn::new('city', 'City')->setField('city'),
            TextColumn::new('invoiceCount', 'Invoices')->setOrderExpression('invoiceCount'),
            TextColumn::new('name', 'Name')->setField('name'),
        ];

        $request = new DataTableRequest(
            1,
            new Columns(['city' => new Column('city', 'city', true, true)]),
            order: [new Order(0, 'asc', 'city'), new Order(1, 'desc', 'invoiceCount'), new Order(2, 'asc', 'name')],
        );

        (new OrderFilter())->apply($qb, $this->context($request, $columns));

        $this->assertEquals([
            ['e.city', DoctrineSortDirection::from('asc')],
            ['invoiceCount', DoctrineSortDirection::from('desc')],
            ['e.name', DoctrineSortDirection::from('asc')],
        ], $calls);
    }

    #[Test]
    public function it_falls_back_to_the_single_order_column_when_an_intent_carries_no_orders(): void
    {
        $column = TextColumn::new('name', 'Name')->setField('name');
        $intent = $this->orderedContext($column, 'desc')->intent;

        $legacyIntent = new DataTableQueryIntent(
            draw: $intent->draw,
            offset: $intent->offset,
            limit: $intent->limit,
            columns: $intent->columns,
            globalSearch: null,
            orderColumn: $intent->orderColumn,
            orderDir: $intent->orderDir,
            columnSearches: [],
            columnControls: [],
        );

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('getDQLPart')->willReturn([]);
        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with('e.name', DoctrineSortDirection::from('desc'));

        (new OrderFilter())->apply($qb, new QueryFilterContext($legacyIntent, ['name' => $column], 'e'));
    }

    #[Test]
    public function it_skips_a_virtual_column_the_root_entity_does_not_map(): void
    {
        $qb = $this->unmappedFieldQueryBuilder('donorProviderName');
        $qb->expects($this->never())->method('addOrderBy');
        $qb->expects($this->never())->method('leftJoin');

        $column = TextColumn::new('donorProviderName', 'Donor');

        (new OrderFilter())->apply($qb, $this->orderedContext($column, 'asc'));
    }

    #[Test]
    public function it_skips_ordering_when_field_requires_an_explicit_scalar_path(): void
    {
        $qb = $this->associationFieldQueryBuilder('client');
        $qb->expects($this->never())->method('addOrderBy');
        $qb->expects($this->never())->method('leftJoin');

        (new OrderFilter())->apply($qb, $this->orderedContext(TextColumn::new('client', 'Client'), 'asc'));
    }

    #[Test]
    public function it_skips_a_virtual_column_even_when_search_field_is_overridden(): void
    {
        $qb = $this->unmappedFieldQueryBuilder('donorProviderName');
        $qb->expects($this->never())->method('addOrderBy');
        $qb->expects($this->never())->method('leftJoin');

        $column = TextColumn::new('donorProviderName', 'Donor')->setSearchField('donorProvider.name');

        (new OrderFilter())->apply($qb, $this->orderedContext($column, 'asc'));
    }

    #[Test]
    public function it_still_orders_a_virtual_column_through_its_order_expression(): void
    {
        $qb = $this->unmappedFieldQueryBuilder('invoiceCount');
        $qb->expects($this->never())->method('leftJoin');
        $qb->expects($this->once())
            ->method('addOrderBy')
            ->with('invoiceCount', DoctrineSortDirection::from('desc'));

        $column = TextColumn::new('invoiceCount', 'Invoices')->setOrderExpression('invoiceCount');

        (new OrderFilter())->apply($qb, $this->orderedContext($column, 'desc'));
    }

    private function orderedContext(ColumnInterface $column, string $direction): QueryFilterContext
    {
        $name    = $column->getName();
        $columns = new Columns([$name => new Column($name, $name, true, true)]);
        $request = new DataTableRequest(1, $columns, order: [new Order(0, $direction, $name)]);

        return $this->context($request, [$column]);
    }
}
