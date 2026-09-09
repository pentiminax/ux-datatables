<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Security;

use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\AuthorizationChecker;
use Pentiminax\UX\DataTables\Security\Permission;
use Pentiminax\UX\DataTables\Security\SecurityVoter;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker as SymfonyAuthorizationChecker;
use Symfony\Component\Security\Core\Authorization\Strategy\UnanimousStrategy;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class RowContextDenyingAuthorizationChecker
{
    public static function create(): AuthorizationChecker
    {
        $strategy = new UnanimousStrategy();

        return new AuthorizationChecker(new SymfonyAuthorizationChecker(
            new TokenStorage(),
            new AccessDecisionManager([
                new SecurityVoter(new AccessDecisionManager([], $strategy)),
                new RowContextDenyingActionVoter(),
            ], $strategy),
        ));
    }
}

/**
 * @extends Voter<string, mixed>
 */
final class RowContextDenyingActionVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        if (\in_array($attribute, [
            Permission::DT_EDIT_ROW,
            Permission::DT_DELETE_ROW,
            Permission::DT_VIEW_ROW_DETAILS,
        ], true)) {
            return \is_object($subject);
        }

        return Permission::DT_EXECUTE_ACTION === $attribute
            && $subject instanceof ActionPermissionContext
            && $subject->hasRowContext;
    }

    protected function voteOnAttribute(
        string $attribute,
        mixed $subject,
        TokenInterface $token,
        ?Vote $vote = null,
    ): bool {
        return Permission::DT_EXECUTE_ACTION !== $attribute;
    }
}
