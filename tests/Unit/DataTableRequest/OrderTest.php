<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(Order::class)]
final class OrderTest extends TestCase
{
    /**
     * @param list<string>         $columnNames the requested columns, in payload order
     * @param array<string, mixed> $orderData   the raw DataTables.net order entry
     */
    #[Test]
    #[DataProvider('provideOrderEntries')]
    public function it_parses_from_array(array $columnNames, array $orderData, Order $expected): void
    {
        $this->assertEquals($expected, Order::fromArray($orderData, self::createColumns(...$columnNames)));
    }

    public static function provideOrderEntries(): iterable
    {
        yield 'first column' => [
            ['email', 'username'],
            ['column' => 0, 'dir' => 'asc'],
            new Order(column: 0, dir: 'asc', name: 'email'),
        ];

        yield 'last column' => [
            ['id', 'name', 'email'],
            ['column' => 2, 'dir' => 'desc'],
            new Order(column: 2, dir: 'desc', name: 'email'),
        ];

        yield 'missing direction defaults to asc' => [
            ['id'],
            ['column' => 0],
            new Order(column: 0, dir: 'asc', name: 'id'),
        ];
    }

    #[Test]
    public function it_handles_invalid_column_index(): void
    {
        $order = Order::fromArray(['column' => 5, 'dir' => 'desc'], self::createColumns('email'));

        $this->assertSame(5, $order->column);
        $this->assertSame('desc', $order->dir);
        $this->assertSame('column_5', $order->name);
    }

    private static function createColumns(string ...$names): Columns
    {
        return Columns::fromRequest(new Request(query: [
            'columns' => array_map(
                static fn (string $name): array => ['data' => $name, 'name' => $name, 'searchable' => true, 'orderable' => true],
                $names,
            ),
        ]));
    }
}
