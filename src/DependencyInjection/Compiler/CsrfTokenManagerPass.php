<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

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
