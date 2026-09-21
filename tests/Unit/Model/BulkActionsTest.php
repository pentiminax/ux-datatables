<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Model;

use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * @internal
 */
#[CoversClass(BulkActions::class)]
final class BulkActionsTest extends TestCase
{
    #[Test]
    public function it_rejects_a_duplicate_name(): void
    {
        $actions = (new BulkActions())->add(BulkAction::new('approve'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Bulk action name "approve" is already used.');

        $actions->add(BulkAction::new('approve'));
    }

    #[Test]
    public function it_looks_an_action_up_by_name(): void
    {
        $approve = BulkAction::new('approve');
        $actions = (new BulkActions())->add($approve)->add(BulkAction::new('archive'));

        $this->assertSame($approve, $actions->get('approve'));
        $this->assertNull($actions->get('unknown'));
    }

    #[Test]
    public function it_allows_selecting_across_pages_unless_told_otherwise(): void
    {
        $this->assertFalse((new BulkActions())->isSelectCurrentPageOnly());
        $this->assertTrue((new BulkActions())->selectCurrentPageOnly()->isSelectCurrentPageOnly());
    }

    #[Test]
    public function it_drops_actions_whose_static_permission_is_denied(): void
    {
        $actions = (new BulkActions())
            ->add(BulkAction::new('approve')->setPermission('ORDER_APPROVE'))
            ->add(BulkAction::new('archive'));

        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $actions->filterStaticPermissions(new AuthorizationChecker($checker), 'App\\OrderTable');

        $this->assertSame(['archive'], array_column($actions->jsonSerialize(), 'name'));
    }

    #[Test]
    public function it_keeps_a_per_row_permission_out_of_the_static_filter(): void
    {
        $actions = (new BulkActions())
            ->add(BulkAction::new('approve')->setPermission('ORDER_APPROVE', static fn (object $o): object => $o));

        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->expects($this->never())->method('isGranted');

        $actions->filterStaticPermissions(new AuthorizationChecker($checker), 'App\\OrderTable');

        $this->assertSame(['approve'], array_column($actions->jsonSerialize(), 'name'));
    }

    #[Test]
    public function cloning_isolates_the_collection_from_its_source(): void
    {
        $actions = (new BulkActions())->add(BulkAction::new('approve'));

        $clone = clone $actions;
        $clone->remove('approve');

        $this->assertTrue($clone->isEmpty());
        $this->assertSame(1, $actions->count());
    }
}
