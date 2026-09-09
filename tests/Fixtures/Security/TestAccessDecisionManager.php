<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Fixtures\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

final class TestAccessDecisionManager implements AccessDecisionManagerInterface
{
    public function decide(TokenInterface $token, array $attributes, mixed $object = null, ?AccessDecision $accessDecision = null): bool
    {
        return !\in_array('ROLE_DENIED', $attributes, true);
    }
}
