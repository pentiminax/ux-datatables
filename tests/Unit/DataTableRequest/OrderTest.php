<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\Columns;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class OrderTest extends TestCase
{
    public function testFromArrayWithValidColumn(): void
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

    public function testFromArrayWithInvalidColumnIndex(): void
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

    public function testFromArrayWithMissingDirection(): void
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

    public function testFromArrayWithMultipleColumns(): void
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
