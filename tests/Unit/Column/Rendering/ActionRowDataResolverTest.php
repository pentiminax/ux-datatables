<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Column\Rendering;

use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(ActionRowDataResolver::class)]
final class ActionRowDataResolverTest extends TestCase
{
    #[Test]
    public function row_unchanged_when_no_action_column(): void
    {
        $resolver = new ActionRowDataResolver();

        $row = ['id' => 1];

        $result = $resolver->resolveRow($row, (object) ['id' => 1], [TextColumn::new('name', 'Name')]);

        $this->assertSame($row, $result);
    }

    #[Test]
    public function adds_authorized_action_to_row(): void
    {
        $actions = new Actions();
        $actions->add(Action::detail()->linkToUrl(static fn (object $r) => '/items/'.$r->id));

        $column = ActionColumn::fromActions('actions', '', $actions);

        $result = (new ActionRowDataResolver())->resolveRow(
            ['id' => 7],
            (object) ['id' => 7],
            [$column],
        );

        $this->assertArrayHasKey(ActionRowDataResolver::ROW_ACTIONS_KEY, $result);
        $this->assertSame(['DETAIL' => ['url' => '/items/7']], $result[ActionRowDataResolver::ROW_ACTIONS_KEY]);
    }

    #[Test]
    public function excludes_per_row_action_when_denied(): void
    {
        $row = (object) ['id' => 7];

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with('EDIT', $row)
            ->willReturn(false);

        $actions = new Actions();
        $actions->add(
            Action::edit()
                ->linkToUrl(static fn (object $r) => '/items/'.$r->id.'/edit')
                ->permission('EDIT', static fn ($r) => $r)
        );

        $column   = ActionColumn::fromActions('actions', '', $actions);
        $resolver = new ActionRowDataResolver(new PermissionChecker($inner));

        $result = $resolver->resolveRow(['id' => 7], $row, [$column]);

        $this->assertArrayNotHasKey(ActionRowDataResolver::ROW_ACTIONS_KEY, $result);
    }

    #[Test]
    public function includes_per_row_action_when_granted(): void
    {
        $row = (object) ['id' => 7];

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner->method('isGranted')->with('EDIT', $row)->willReturn(true);

        $actions = new Actions();
        $actions->add(
            Action::edit()
                ->linkToUrl(static fn (object $r) => '/items/'.$r->id.'/edit')
                ->permission('EDIT', static fn ($r) => $r)
        );

        $column   = ActionColumn::fromActions('actions', '', $actions);
        $resolver = new ActionRowDataResolver(new PermissionChecker($inner));

        $result = $resolver->resolveRow(['id' => 7], $row, [$column]);

        $this->assertSame(
            ['EDIT' => ['url' => '/items/7/edit']],
            $result[ActionRowDataResolver::ROW_ACTIONS_KEY],
        );
    }

    #[Test]
    public function passes_resolved_subject_to_authorization_checker(): void
    {
        $row = (object) ['owner' => 'alice'];

        $inner = $this->createMock(AuthorizationCheckerInterface::class);
        $inner
            ->expects($this->once())
            ->method('isGranted')
            ->with('OWNS', 'alice')
            ->willReturn(true);

        $actions = new Actions();
        $actions->add(
            Action::edit()
                ->linkToUrl(static fn (object $r) => '/items/'.$r->owner)
                ->permission('OWNS', static fn (object $r) => $r->owner)
        );

        $column   = ActionColumn::fromActions('actions', '', $actions);
        $resolver = new ActionRowDataResolver(new PermissionChecker($inner));

        $resolver->resolveRow([], $row, [$column]);
    }

    #[Test]
    public function no_op_fallback_when_permission_checker_is_null(): void
    {
        // The PermissionChecker without inner checker grants everything; per-row permission still appears.
        $actions = new Actions();
        $actions->add(
            Action::edit()
                ->linkToUrl(static fn (object $r) => '/items/'.$r->id)
                ->permission('EDIT', static fn ($r) => $r)
        );

        $column = ActionColumn::fromActions('actions', '', $actions);

        $result = (new ActionRowDataResolver())->resolveRow(
            [],
            (object) ['id' => 7],
            [$column],
        );

        $this->assertSame(
            ['EDIT' => ['url' => '/items/7']],
            $result[ActionRowDataResolver::ROW_ACTIONS_KEY],
        );
    }
}
