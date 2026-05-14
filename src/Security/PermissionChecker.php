<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Security;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationCredentialsNotFoundException;

/**
 * Thin wrapper around Symfony's AuthorizationChecker so the bundle keeps working
 * when the security stack is unavailable (CLI, missing firewall, tests).
 *
 * If no checker is provided, every permission is granted (no-op fallback).
 */
final class PermissionChecker
{
    public function __construct(private readonly ?AuthorizationCheckerInterface $checker = null)
    {
    }

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        if (null === $this->checker) {
            return true;
        }

        try {
            return $this->checker->isGranted($attribute, $subject);
        } catch (AuthenticationCredentialsNotFoundException) {
            return false;
        }
    }
}
