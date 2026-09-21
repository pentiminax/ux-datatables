<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Thin wrapper around Symfony's AuthorizationChecker.
 *
 * If no checker is provided, an empty or null attribute (no permission configured) and the
 * bundle's own Permission::DT_* attributes are granted; an application permission fails
 * closed with a LogicException rather than silently granting access.
 */
final class AuthorizationChecker implements AuthorizationCheckerInterface
{
    public function __construct(
        private readonly ?AuthorizationCheckerInterface $checker = null,
    ) {
    }

    public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
    {
        if (null === $attribute || '' === $attribute) {
            return true;
        }

        if (null === $this->checker && \in_array($attribute, Permission::all(), true)) {
            // Without the SecurityBundle the bundle's own SecurityVoter is not registered
            // either: there is nothing to vote on, and an application with no firewall must
            // keep rendering its tables. Application permissions still fail closed below.
            return true;
        }

        if (null === $this->checker) {
            throw new \LogicException(\sprintf('A permission "%s" is configured but no Symfony authorization checker is available. Enable the SecurityBundle (a firewall must be configured) or remove the permission.', \is_string($attribute) || $attribute instanceof \Stringable ? (string) $attribute : get_debug_type($attribute)));
        }

        try {
            return $this->checker->isGranted($attribute, $subject, $accessDecision);
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        }
    }
}
