<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Security;

use Pentiminax\UX\DataTables\Security\ActionPermissionContext;
use Pentiminax\UX\DataTables\Security\Permission;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class TestAuthorizationChecker implements AuthorizationCheckerInterface
{
    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if (Permission::DT_EXECUTE_ACTION === $attribute && $subject instanceof ActionPermissionContext) {
            return 'ROLE_DENIED' !== $subject->action->getPermission();
        }

        return 'ROLE_DENIED' !== $attribute;
    }
}
