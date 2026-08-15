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
use Pentiminax\UX\DataTables\Query\Filter\OrderFilter;
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
            ->with($expectedExpression, $direction);

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

    private function orderedContext(ColumnInterface $column, string $direction): QueryFilterContext
    {
        $name    = $column->getName();
        $columns = new Columns([$name => new Column($name, $name, true, true)]);
        $request = new DataTableRequest(1, $columns, order: [new Order(0, $direction, $name)]);

        return $this->context($request, [$column]);
    }
}
