<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(Actions::class)]
final class ActionsTest extends TestCase
{
    #[Test]
    public function it_starts_empty_with_default_column_metadata(): void
    {
        $actions = new Actions();

        $this->assertTrue($actions->isEmpty());
        $this->assertSame(0, $actions->count());
        $this->assertSame([], $actions->getActions());
        $this->assertSame('Actions', $actions->getColumnLabel());
        $this->assertNull($actions->getColumnClassName());
        $this->assertSame(ActionsPosition::AfterColumns, $actions->getPosition());
        $this->assertNull($actions->getAlignment());
    }

    #[Test]
    public function it_adds_and_removes_actions(): void
    {
        $actions = (new Actions())->add(Action::delete());

        $this->assertFalse($actions->isEmpty());
        $this->assertSame(1, $actions->count());
        $this->assertSame(ActionType::Delete, $actions->getActions()[0]->getType());

        $actions->remove(ActionType::Delete);

        $this->assertTrue($actions->isEmpty());
        $this->assertSame(0, $actions->count());
    }

    #[Test]
    public function it_configures_column_metadata_fluently(): void
    {
        $actions = (new Actions())
            ->setColumnLabel('Operations')
            ->setColumnClassName('dt-center')
            ->position(ActionsPosition::BeforeColumns)
            ->alignment(ActionsAlignment::Center);

        $this->assertSame('Operations', $actions->getColumnLabel());
        $this->assertSame('dt-center', $actions->getColumnClassName());
        $this->assertSame(ActionsPosition::BeforeColumns, $actions->getPosition());
        $this->assertSame(ActionsAlignment::Center, $actions->getAlignment());
        $this->assertSame('dt-center', $actions->getAlignment()->cssClass());
    }

    #[Test]
    public function it_serializes_every_action_in_insertion_order(): void
    {
        $actions = (new Actions())
            ->add(Action::delete()->label('Remove'))
            ->add(Action::new('view', 'View')->linkToUrl('/invoices/1'))
            ->add(Action::new('download', 'Download')->linkToUrl('/invoices/1/download'));

        $json = $actions->jsonSerialize();

        $this->assertSame(3, $actions->count());
        $this->assertCount(3, $json);
        $this->assertSame(['DELETE', 'CUSTOM', 'CUSTOM'], array_column($json, 'type'));
        $this->assertSame(['Remove', 'View', 'Download'], array_column($json, 'label'));
    }

    public static function invalidActionProvider(): iterable
    {
        yield 'duplicate native name' => [
            [Action::delete()->label('First'), Action::delete()->label('Second')],
            'Action name "DELETE" is already used.',
        ];

        yield 'duplicate custom name' => [
            [Action::new('view', 'First'), Action::new('view', 'Second')],
            'Action name "view" is already used.',
        ];

        yield 'empty custom name' => [
            [Action::new('   ')],
            'Action name must not be empty.',
        ];

        yield 'name reserved for a native action' => [
            [Action::new('delete')],
            'Custom action name "delete" is reserved.',
        ];
    }

    /**
     * @param list<Action> $added
     */
    #[Test]
    #[DataProvider('invalidActionProvider')]
    public function it_rejects_invalid_action_names(array $added, string $message): void
    {
        $actions = new Actions();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        foreach ($added as $action) {
            $actions->add($action);
        }
    }

    #[Test]
    public function it_removes_actions_whose_static_permission_is_denied(): void
    {
        $actions = (new Actions())
            ->add(Action::delete()->permission('ROLE_ADMIN'))
            ->add(Action::edit()->permission('ROLE_EDITOR'))
            ->add(Action::detail());

        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, false],
            ['ROLE_EDITOR', null, true],
        ]);

        $actions->filterStaticPermissions(new PermissionChecker($checker));

        $types = array_map(static fn (Action $action) => $action->getType(), $actions->getActions());
        $this->assertSame([ActionType::Edit, ActionType::Detail], $types);
    }

    #[Test]
    public function it_ignores_per_row_permissions_when_filtering(): void
    {
        $actions = (new Actions())->add(Action::delete()->permission('DELETE', static fn ($row) => $row));

        $actions->filterStaticPermissions(new PermissionChecker($this->createStub(AuthorizationCheckerInterface::class)));

        $this->assertSame(1, $actions->count());
    }

    #[Test]
    public function it_keeps_every_action_without_an_authorization_checker(): void
    {
        $actions = (new Actions())->add(Action::delete()->permission('ROLE_ADMIN'));

        $actions->filterStaticPermissions(new PermissionChecker());

        $this->assertSame(1, $actions->count());
    }

    #[Test]
    public function it_groups_actions_by_their_effective_position(): void
    {
        $inherited = (new Actions())
            ->add(Action::detail())
            ->add(Action::edit())
            ->partitionByPosition();

        $this->assertSame([ActionsPosition::AfterColumns->value], array_keys($inherited));
        $this->assertSame(2, $inherited[ActionsPosition::AfterColumns->value]->count());

        $groups = (new Actions())
            ->position(ActionsPosition::BeforeColumns)
            ->add(Action::detail())
            ->add(Action::edit()->position(ActionsPosition::AfterColumns))
            ->add(Action::delete())
            ->partitionByPosition();

        $before = $groups[ActionsPosition::BeforeColumns->value];
        $after  = $groups[ActionsPosition::AfterColumns->value];

        $this->assertSame(2, $before->count());
        $this->assertSame(ActionType::Detail, $before->getActions()[0]->getType());
        $this->assertSame(ActionType::Delete, $before->getActions()[1]->getType());
        $this->assertSame(1, $after->count());
        $this->assertSame(ActionType::Edit, $after->getActions()[0]->getType());
    }

    #[Test]
    public function it_copies_the_column_metadata_into_every_group(): void
    {
        $actions = (new Actions())
            ->setColumnLabel('Ops')
            ->setColumnClassName('dt-center')
            ->alignment(ActionsAlignment::Center)
            ->add(Action::detail()->position(ActionsPosition::BeforeColumns));

        $group = $actions->partitionByPosition()[ActionsPosition::BeforeColumns->value];

        $this->assertSame('Ops', $group->getColumnLabel());
        $this->assertSame('dt-center', $group->getColumnClassName());
        $this->assertSame(ActionsAlignment::Center, $group->getAlignment());
    }
}
