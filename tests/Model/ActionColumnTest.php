<?php

namespace Pentiminax\UX\DataTables\Tests\Model;

use Pentiminax\UX\DataTables\Enum\Action;
use Pentiminax\UX\DataTables\Model\ActionColumn;
use PHPUnit\Framework\TestCase;

class ActionColumnTest extends TestCase
{
    public function testCreateActionColumn(): void
    {
        $column = ActionColumn::new(
            name: 'actions',
            title: 'Actions',
            action: Action::DELETE,
            actionLabel: 'Delete',
            actionUrl: '/delete',
        );

        $expectedArray = [
            'data' => null,
            'className' => 'not-exportable',
            'name' => 'actions',
            'title' => 'Actions',
            'action' => Action::DELETE->value,
            'actionLabel' => 'Delete',
            'actionUrl' => '/delete',
        ];

        $this->assertEquals($expectedArray, $column->jsonSerialize());
    }
}