<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Enum\Icon;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
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
    public function it_preserves_field_and_visibility_when_serializing_actions(): void
    {
        $column = ActionColumn::fromActions(
            'actions',
            'Actions',
            (new Actions())->add(
                Action::detail()
                    ->label('View')
                    ->icon(Icon::Eye)
                    ->linkToUrl('/books/42')
            )
        )
            ->setField('bookActions')
            ->setVisible(false);

        $data = $column->jsonSerialize();

        $this->assertSame('actions', $data['name']);
        $this->assertSame('Actions', $data['title']);
        $this->assertSame('__ux_datatables_actions', $data['data']);
        $this->assertSame(1, $data['responsivePriority']);
        $this->assertSame('bookActions', $data['field']);
        $this->assertFalse($data['visible']);
        $this->assertSame('not-exportable', $data['className']);
        $this->assertFalse($data['orderable']);
        $this->assertFalse($data['searchable']);
        $this->assertSame([], $data['columnControl']);
        $this->assertCount(1, $data['actions']);
        $this->assertSame('DETAIL', $data['actions'][0]['type']);
        $this->assertSame('eye', $data['actions'][0]['lucideIcon']);
        $this->assertSame('/books/42', $data['actions'][0]['url']);
    }

    #[Test]
    public function it_clones_the_actions_collection(): void
    {
        $actions = (new Actions())->add(Action::delete());
        $column  = ActionColumn::fromActions('actions', '', $actions);

        $clone = clone $column;
        $clone->getActions()?->remove(ActionType::Delete);

        $this->assertSame(1, $actions->count());
        $this->assertTrue($clone->getActions()?->isEmpty());
    }
}
