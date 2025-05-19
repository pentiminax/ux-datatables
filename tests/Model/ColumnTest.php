<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Enum\ColumnType;
use Pentiminax\UX\DataTables\Model\Column;
use PHPUnit\Framework\TestCase;

class ColumnTest extends TestCase
{
    public function testColumnToArray(): void
    {
        $column = Column::new('name', 'Name', ColumnType::STRING, true)
            ->setClassName('text-center')
            ->setCellType('th')
            ->setOrderable(false)
            ->setSearchable(false)
            ->setWidth('100px');

        $expectedArray = [
            'name' => 'name',
            'title' => 'Name',
            'className' => 'text-center',
            'data' => 'name',
            'cellType' => 'th',
            'orderable' => false,
            'searchable' => false,
            'width' => '100px',
            'type' => 'string',
            'visible' => true,
        ];

        $this->assertEquals($expectedArray, $column->jsonSerialize());
    }
}