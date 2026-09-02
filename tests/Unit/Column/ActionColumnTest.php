<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
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
    public function it_applies_the_actions_column_class_name_and_alignment(): void
    {
        $actions = (new Actions())
            ->setColumnClassName('w-1')
            ->alignment(ActionsAlignment::Center)
            ->add(Action::delete());

        $column = ActionColumn::fromActions('actions', 'Actions', $actions);

        $this->assertSame(\sprintf('w-1 %s', ActionsAlignment::Center->cssClass()), $column->getClassName());
    }

    #[Test]
    public function it_applies_the_actions_column_control(): void
    {
        $actions = (new Actions())
            ->setColumnControl(['colvisDropdown'])
            ->add(Action::delete());

        $column = ActionColumn::fromActions('actions', 'Actions', $actions);

        $this->assertSame(['colvisDropdown'], $column->jsonSerialize()['columnControl']);
    }

    #[Test]
    public function it_leaves_the_class_name_untouched_when_the_actions_configure_none(): void
    {
        $column = ActionColumn::fromActions('actions', 'Actions', (new Actions())->add(Action::delete()));

        $this->assertNull($column->getClassName());
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

    #[Test]
    public function it_allows_overriding_the_default_responsive_priority(): void
    {
        $column = ActionColumn::fromActions(
            'actions',
            'Actions',
            (new Actions())->add(Action::detail()->linkToUrl('/books/42'))
        );

        $this->assertSame(1, $column->getResponsivePriority());
        $this->assertSame(1, $column->jsonSerialize()['responsivePriority']);

        $column->setResponsivePriority(2);

        $this->assertSame(2, $column->getResponsivePriority());
        $this->assertSame(2, $column->jsonSerialize()['responsivePriority']);
    }
}
