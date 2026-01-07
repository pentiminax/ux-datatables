<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\DataTableRequest;

use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\DataTableRequest\Order;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class DataTableRequestTest extends TestCase
{
    public function testFromRequestWithOrdering(): void
    {
        $request = new Request(
            query: [
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true],
                    ['data' => 'name', 'name' => 'name', 'searchable' => true, 'orderable' => true],
                    ['data' => 'email', 'name' => 'email', 'searchable' => true, 'orderable' => true],
                ],
                'order' => [
                    ['column' => 1, 'dir' => 'asc'],
                    ['column' => 0, 'dir' => 'desc'],
                ],
                'search' => [
                    'value' => '',
                    'regex' => false,
                ],
            ]
        );

        $dataTableRequest = DataTableRequest::fromRequest($request);

        // Verify orders are Order objects, not arrays
        $this->assertIsArray($dataTableRequest->order);
        $this->assertCount(2, $dataTableRequest->order);
        $this->assertInstanceOf(Order::class, $dataTableRequest->order[0]);
        $this->assertInstanceOf(Order::class, $dataTableRequest->order[1]);

        // Verify first order
        $this->assertEquals(1, $dataTableRequest->order[0]->column);
        $this->assertEquals('asc', $dataTableRequest->order[0]->dir);
        $this->assertEquals('name', $dataTableRequest->order[0]->name);

        // Verify second order
        $this->assertEquals(0, $dataTableRequest->order[1]->column);
        $this->assertEquals('desc', $dataTableRequest->order[1]->dir);
        $this->assertEquals('id', $dataTableRequest->order[1]->name);
    }

    public function testFromRequestWithEmptyOrdering(): void
    {
        $request = new Request(
            query: [
                'draw'    => 1,
                'start'   => 0,
                'length'  => 10,
                'columns' => [
                    ['data' => 'id', 'name' => 'id', 'searchable' => true, 'orderable' => true],
                ],
                'order'  => [],
                'search' => [
                    'value' => '',
                    'regex' => false,
                ],
            ]
        );

        $dataTableRequest = DataTableRequest::fromRequest($request);

        $this->assertIsArray($dataTableRequest->order);
        $this->assertCount(0, $dataTableRequest->order);
    }

    public function testFromRequestWithAllProperties(): void
    {
        $request = new Request(
            query: [
                'draw'    => 5,
                'start'   => 20,
                'length'  => 25,
                'columns' => [
                    ['data' => 'username', 'name' => 'username', 'searchable' => true, 'orderable' => true],
                    ['data' => 'email', 'name' => 'email', 'searchable' => true, 'orderable' => true],
                ],
                'order' => [
                    ['column' => 0, 'dir' => 'desc'],
                ],
                'search' => [
                    'value' => 'test',
                    'regex' => false,
                ],
            ]
        );

        $dataTableRequest = DataTableRequest::fromRequest($request);

        // Verify all properties
        $this->assertEquals(5, $dataTableRequest->draw);
        $this->assertEquals(20, $dataTableRequest->start);
        $this->assertEquals(25, $dataTableRequest->length);
        $this->assertNotNull($dataTableRequest->search);
        $this->assertEquals('test', $dataTableRequest->search->value);
        $this->assertNotNull($dataTableRequest->columns);

        // Verify order
        $this->assertCount(1, $dataTableRequest->order);
        $this->assertInstanceOf(Order::class, $dataTableRequest->order[0]);
        $this->assertEquals(0, $dataTableRequest->order[0]->column);
        $this->assertEquals('desc', $dataTableRequest->order[0]->dir);
        $this->assertEquals('username', $dataTableRequest->order[0]->name);
    }
}
