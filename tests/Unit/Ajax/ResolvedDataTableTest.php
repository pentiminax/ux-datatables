<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Ajax;

use Pentiminax\UX\DataTables\Ajax\ResolvedDataTable;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Exception\DuplicateActionNameException;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ResolvedDataTable::class)]
final class ResolvedDataTableTest extends TestCase
{
    #[Test]
    public function it_returns_null_when_no_action_of_the_requested_type_exists(): void
    {
        $resolved = new ResolvedDataTable(new NoActionsDataTableFixture(), NoActionsDataTableFixture::class, NoActionsDataTableFixture::class);

        $this->assertNull($resolved->findAction(ActionType::Delete));
    }

    #[Test]
    public function it_returns_the_single_matching_action(): void
    {
        $resolved = new ResolvedDataTable(new SingleDeleteActionDataTableFixture(), SingleDeleteActionDataTableFixture::class, SingleDeleteActionDataTableFixture::class);

        $action = $resolved->findAction(ActionType::Delete);

        $this->assertNotNull($action);
        $this->assertSame(ActionType::Delete, $action->getType());
    }

    /**
     * Two `ActionColumn::fromActions()` columns, each carrying its own `Actions` collection, can
     * both declare a `DELETE` action. `Actions::add()` only rejects duplicates within a single
     * collection, so this collision is only caught here.
     */
    #[Test]
    public function it_throws_when_two_action_columns_share_a_colliding_action_name(): void
    {
        $resolved = new ResolvedDataTable(new DuplicateDeleteActionsDataTableFixture(), DuplicateDeleteActionsDataTableFixture::class, DuplicateDeleteActionsDataTableFixture::class);

        $this->expectException(DuplicateActionNameException::class);

        $resolved->findAction(ActionType::Delete);
    }
}

final class NoActionsDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name', 'Name');
    }
}

final class SingleDeleteActionDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        $actions = new Actions();
        $actions->add(Action::delete());

        yield ActionColumn::fromActions('actions', '', $actions);
    }
}

final class DuplicateDeleteActionsDataTableFixture extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        $first = new Actions();
        $first->add(Action::delete());

        $second = new Actions();
        $second->add(Action::delete());

        yield ActionColumn::fromActions('actions_1', '', $first);
        yield ActionColumn::fromActions('actions_2', '', $second);
    }
}
