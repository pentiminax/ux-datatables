<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(Order::class)]
final class OrderTest extends TestCase
{
    #[Test]
    public function it_parses_from_array_with_valid_column(): void
    {
        $request = new Request(
            query: [
                'columns' => [
                    ['data' => 'email', 'name' => 'email', 'searchable' => true, 'orderable' => true],
                    ['data' => 'username', 'name' => 'username', 'searchable' => true, 'orderable' => true],
                ],
            ]
        );

        $columns   = Columns::fromRequest($request);
        $orderData = ['column' => 0, 'dir' => 'asc'];

        $order = Order::fromArray($orderData, $columns);

        $this->assertEquals(0, $order->column);
        $this->assertEquals('asc', $order->dir);
        $this->assertEquals('email', $order->name);
    }

    #[Test]
    public function it_handles_invalid_column_index(): void
    {
        $request = new Request(
            query: [
                'columns' => [
                    ['data' => 'email', 'name' => 'email', 'searchable' => true, 'orderable' => true],
                ],
            ]
        );

        $columns   = Columns::fromRequest($request);
        $orderData = ['column' => 5, 'dir' => 'desc'];

        $order = Order::fromArray($orderData, $columns);

        $this->assertEquals(5, $order->column);
        $this->assertEquals('desc', $order->dir);
        $this->assertEquals('column_5', $order->name);
    }

    #[Test]
    public function it_defaults_direction_when_missing(): void
    {
        $request = new Request(
            query: [
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true],
                ],
            ]
        );

        $columns   = Columns::fromRequest($request);
        $orderData = ['column' => 0];

        $order = Order::fromArray($orderData, $columns);

        $this->assertEquals(0, $order->column);
        $this->assertEquals('asc', $order->dir);
        $this->assertEquals('id', $order->name);
    }

    #[Test]
    public function it_parses_from_array_with_multiple_columns(): void
    {
        $request = new Request(
            query: [
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
                    ['data' => 'email', 'name' => 'email', 'searchable' => true, 'orderable' => true],
                ],
            ]
        );

        $columns   = Columns::fromRequest($request);
        $orderData = ['column' => 2, 'dir' => 'desc'];

        $order = Order::fromArray($orderData, $columns);

        $this->assertEquals(2, $order->column);
        $this->assertEquals('desc', $order->dir);
        $this->assertEquals('email', $order->name);
    }
}
