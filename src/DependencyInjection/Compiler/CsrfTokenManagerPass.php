<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Prefers the application's configured CSRF token manager for the mutation guard,
 * falling back to the bundle-provided session-backed manager when the application
 * has no CSRF manager wired.
 *
 * This keeps the delete and boolean-toggle endpoints protected out of the box: the
 * guard always has a manager to validate against (fail closed), while the bundle
 * still works when the host application has not configured Symfony's CSRF component.
 */
final class CsrfTokenManagerPass implements CompilerPassInterface
{
    public const MANAGER_ID = 'datatables.security.csrf_token_manager';

    private const APP_MANAGER_ID = 'security.csrf.token_manager';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(self::MANAGER_ID) || !$container->has(self::APP_MANAGER_ID)) {
            return;
        }

        $container->removeDefinition(self::MANAGER_ID);
        $container->setAlias(self::MANAGER_ID, self::APP_MANAGER_ID);
    }
}
