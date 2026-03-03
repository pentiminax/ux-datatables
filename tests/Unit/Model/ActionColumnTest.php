<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Enum\Action;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ActionColumn::class)]
final class ActionColumnTest extends TestCase
{
    #[Test]
    public function it_creates_action_column(): void
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
