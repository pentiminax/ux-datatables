<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Enum\ActionsAlignment;
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\ActionType;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
class ActionsTest extends TestCase
{
    public function test_new_actions_is_empty(): void
    {
        $actions = new Actions();

        $this->assertTrue($actions->isEmpty());
        $this->assertSame(0, $actions->count());
        $this->assertSame([], $actions->getActions());
    }

    public function test_add_action(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete());

        $this->assertFalse($actions->isEmpty());
        $this->assertSame(1, $actions->count());
    }

    public function test_remove_action(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete());
        $actions->remove(ActionType::Delete);

        $this->assertTrue($actions->isEmpty());
    }

    public function test_set_column_label(): void
    {
        $actions = new Actions();
        $actions->setColumnLabel('Operations');

        $this->assertSame('Operations', $actions->getColumnLabel());
    }

    public function test_default_column_label(): void
    {
        $actions = new Actions();

        $this->assertSame('Actions', $actions->getColumnLabel());
    }

    public function test_set_column_class_name(): void
    {
        $actions = new Actions();
        $actions->setColumnClassName('dt-center');

        $this->assertSame('dt-center', $actions->getColumnClassName());
    }

    public function test_json_serialize(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->label('Remove'));

        $json = $actions->jsonSerialize();

        $this->assertCount(1, $json);
        $this->assertSame('DELETE', $json[0]['type']);
        $this->assertSame('Remove', $json[0]['label']);
    }

    public function test_fluent_api(): void
    {
        $actions = (new Actions())
            ->add(Action::delete())
            ->setColumnLabel('Ops');

        $this->assertSame(1, $actions->count());
        $this->assertSame('Ops', $actions->getColumnLabel());
    }

    public function test_add_rejects_a_duplicate_native_action_name(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->label('First'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action name "DELETE" is already used.');

        $actions->add(Action::delete()->label('Second'));
    }

    public function test_custom_actions_with_distinct_names_coexist(): void
    {
        $actions = new Actions();
        $actions->add(Action::new('view', 'View')->linkToUrl('/invoices/1'));
        $actions->add(Action::new('download', 'Download')->linkToUrl('/invoices/1/download'));

        $this->assertSame(2, $actions->count());

        $labels = array_map(static fn (Action $a) => $a->jsonSerialize()['label'], $actions->getActions());
        $this->assertSame(['View', 'Download'], $labels);
    }

    public function test_add_rejects_a_duplicate_custom_action_name(): void
    {
        $actions = new Actions();
        $actions->add(Action::new('view', 'First'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action name "view" is already used.');

        $actions->add(Action::new('view', 'Second'));
    }

    public function test_add_rejects_an_empty_custom_action_name(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Action name must not be empty.');

        (new Actions())->add(Action::new('   '));
    }

    public function test_add_rejects_custom_names_reserved_for_native_actions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Custom action name "delete" is reserved.');

        (new Actions())->add(Action::new('delete'));
    }

    public function test_filter_static_permissions_removes_denied_actions(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->permission('ROLE_ADMIN'));
        $actions->add(Action::edit()->permission('ROLE_EDITOR'));
        $actions->add(Action::detail()); // no permission

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->willReturnMap([
            ['ROLE_ADMIN', null, false],
            ['ROLE_EDITOR', null, true],
        ]);

        $actions->filterStaticPermissions(new PermissionChecker($inner));

        $types = array_map(static fn (Action $a) => $a->getType(), $actions->getActions());
        $this->assertSame([ActionType::Edit, ActionType::Detail], $types);
    }

    public function test_filter_static_permissions_ignores_per_row_actions(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->permission('DELETE', static fn ($row) => $row));

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->expects($this->never())->method('isGranted');

        $actions->filterStaticPermissions(new PermissionChecker($inner));

        $this->assertSame(1, $actions->count());
    }

    public function test_filter_static_permissions_with_no_checker_is_noop(): void
    {
        $actions = new Actions();
        $actions->add(Action::delete()->permission('ROLE_ADMIN'));

        $actions->filterStaticPermissions(new PermissionChecker());

        $this->assertSame(1, $actions->count());
    }

    public function test_position_defaults_to_after_columns(): void
    {
        $this->assertSame(ActionsPosition::AfterColumns, (new Actions())->getPosition());
    }

    public function test_alignment_defaults_to_null(): void
    {
        $this->assertNull((new Actions())->getAlignment());
    }

    public function test_set_position_and_alignment_are_fluent(): void
    {
        $actions = (new Actions())
            ->position(ActionsPosition::BeforeColumns)
            ->alignment(ActionsAlignment::Center);

        $this->assertSame(ActionsPosition::BeforeColumns, $actions->getPosition());
        $this->assertSame(ActionsAlignment::Center, $actions->getAlignment());
        $this->assertSame('dt-center', $actions->getAlignment()->cssClass());
    }

    public function test_partition_groups_all_actions_under_collection_position_when_no_override(): void
    {
        $actions = (new Actions())
            ->add(Action::detail())
            ->add(Action::edit());

        $groups = $actions->partitionByPosition();

        $this->assertSame([ActionsPosition::AfterColumns->value], array_keys($groups));
        $this->assertSame(2, $groups[ActionsPosition::AfterColumns->value]->count());
    }

    public function test_partition_splits_actions_by_per_action_position(): void
    {
        $actions = (new Actions())
            ->add(Action::detail()->position(ActionsPosition::BeforeColumns))
            ->add(Action::edit())
            ->add(Action::delete());

        $groups = $actions->partitionByPosition();

        $this->assertArrayHasKey(ActionsPosition::BeforeColumns->value, $groups);
        $this->assertArrayHasKey(ActionsPosition::AfterColumns->value, $groups);

        $before = $groups[ActionsPosition::BeforeColumns->value];
        $after  = $groups[ActionsPosition::AfterColumns->value];

        $this->assertSame(1, $before->count());
        $this->assertSame(ActionType::Detail, $before->getActions()[0]->getType());
        $this->assertSame(2, $after->count());
    }

    public function test_partition_groups_inherit_column_metadata(): void
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

    public function test_partition_respects_collection_position_as_fallback(): void
    {
        $actions = (new Actions())
            ->position(ActionsPosition::BeforeColumns)
            ->add(Action::detail()) // inherits BeforeColumns
            ->add(Action::edit()->position(ActionsPosition::AfterColumns));

        $groups = $actions->partitionByPosition();

        $this->assertSame(1, $groups[ActionsPosition::BeforeColumns->value]->count());
        $this->assertSame(ActionType::Detail, $groups[ActionsPosition::BeforeColumns->value]->getActions()[0]->getType());
        $this->assertSame(ActionType::Edit, $groups[ActionsPosition::AfterColumns->value]->getActions()[0]->getType());
    }
}
