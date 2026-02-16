<?php

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Enum\Action;
use PHPUnit\Framework\TestCase;

class ActionColumnTest extends TestCase
{
    public function testCreateActionColumn(): void
    {
        $column = ActionColumn::new(
            name: 'actions',
            title: 'Actions',
            actionUrl: '/delete',
        );

        $expectedArray = [
            'data'        => null,
            'className'   => 'not-exportable',
            'name'        => 'actions',
            'title'       => 'Actions',
            'action'      => Action::DELETE->value,
            'actionLabel' => 'Delete',
            'actionUrl'   => '/delete',
            'searchable'  => false,
            'orderable'   => false,
        ];

        $this->assertEquals($expectedArray, $column->jsonSerialize());
    }
}
