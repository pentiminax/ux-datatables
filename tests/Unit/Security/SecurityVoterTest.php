<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Unit\Security;

use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Action;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Security\SecurityVoter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * @internal
 */
#[CoversClass(ActionPermissionContext::class)]
#[CoversClass(Permission::class)]
#[CoversClass(SecurityVoter::class)]
final class SecurityVoterTest extends TestCase
{
    #[Test]
    public function table_access_delegates_the_configured_permission_with_the_table_subject(): void
    {
        $table = new VoterPermissionDataTable('VIEW_REPORTS');
        $token = $this->createToken();

        $decisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $decisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($token, ['VIEW_REPORTS'], $table)
            ->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            (new SecurityVoter($decisionManager))->vote($token, $table, [Permission::DT_ACCESS_TABLE])
        );
    }

    #[Test]
    public function table_access_grants_when_the_table_has_no_configured_permission(): void
    {
        $decisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $decisionManager->expects($this->never())->method('decide');

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new SecurityVoter($decisionManager))->vote(
                $this->createToken(),
                new VoterPermissionDataTable(),
                [Permission::DT_ACCESS_TABLE]
            )
        );
    }

    #[Test]
    public function static_action_permission_delegates_with_null_subject(): void
    {
        $action  = Action::edit()->permission('EDIT_REPORTS');
        $context = new ActionPermissionContext(VoterPermissionDataTable::class, $action, ['id' => 10], false);
        $token   = $this->createToken();

        $decisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $decisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($token, ['EDIT_REPORTS'], null)
            ->willReturn(true);

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new SecurityVoter($decisionManager))->vote($token, $context, [Permission::DT_EXECUTE_ACTION])
        );
    }

    #[Test]
    public function per_row_action_permission_delegates_with_the_resolved_row_subject(): void
    {
        $source = ['owner' => 'alice'];
        $action = Action::delete()->permission('DELETE_REPORT', static fn (array $row): string => $row['owner']);
        $token  = $this->createToken();

        $decisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $decisionManager
            ->expects($this->once())
            ->method('decide')
            ->with($token, ['DELETE_REPORT'], 'alice')
            ->willReturn(false);

        $this->assertSame(
            VoterInterface::ACCESS_DENIED,
            (new SecurityVoter($decisionManager))->vote(
                $token,
                new ActionPermissionContext(VoterPermissionDataTable::class, $action, $source, true),
                [Permission::DT_EXECUTE_ACTION]
            )
        );
    }

    #[Test]
    public function per_row_action_resolver_is_not_called_without_row_context(): void
    {
        $action = Action::delete()->permission('DELETE_REPORT', static function (): never {
            throw new \LogicException('The row resolver must not run.');
        });

        $decisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $decisionManager->expects($this->never())->method('decide');

        $this->assertSame(
            VoterInterface::ACCESS_GRANTED,
            (new SecurityVoter($decisionManager))->vote(
                $this->createToken(),
                new ActionPermissionContext(VoterPermissionDataTable::class, $action, null, false),
                [Permission::DT_EXECUTE_ACTION]
            )
        );
    }

    #[Test]
    public function unsupported_permission_abstains(): void
    {
        $decisionManager = $this->createMock(AccessDecisionManagerInterface::class);
        $decisionManager->expects($this->never())->method('decide');

        $this->assertSame(
            VoterInterface::ACCESS_ABSTAIN,
            (new SecurityVoter($decisionManager))->vote(
                $this->createToken(),
                new VoterPermissionDataTable('VIEW_REPORTS'),
                ['ROLE_ADMIN']
            )
        );
    }

    private function createToken(): TokenInterface
    {
        return $this->createStub(TokenInterface::class);
    }
}

final class VoterPermissionDataTable extends AbstractDataTable
{
    public function __construct(
        private readonly ?string $permission = null,
    ) {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return null === $this->permission ? $table : $table->setPermission($this->permission);
    }
}
