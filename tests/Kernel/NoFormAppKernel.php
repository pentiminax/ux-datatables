<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Boots the bundle as if symfony/form were not installed: the edit form services are not registered.
 */
final class NoFormAppKernel extends TestHarnessAppKernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables_harness_no_form/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables_harness_no_form/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->removeDefinition('datatables.controller.ajax_edit_form');
                $container->removeDefinition('datatables.controller.ajax_edit_form_submit');
                $container->getDefinition('datatables.route_loader')->setArgument(0, false);
            }
        }, PassConfig::TYPE_BEFORE_OPTIMIZATION, 100);
    }
}
