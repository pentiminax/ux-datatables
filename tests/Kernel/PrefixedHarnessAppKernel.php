<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Imports the bundle routes under a prefix, the way an application is free to.
 */
final class PrefixedHarnessAppKernel extends TestHarnessAppKernel
{
    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables_harness_prefixed/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables_harness_prefixed/log';
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('datatables.route_loader::loadRoutes', 'service')->prefix('/admin');

        $routes->add('harness_books', '/books')->controller([HarnessController::class, 'books']);
        $routes->add('harness_two_tables', '/two-tables')->controller([HarnessController::class, 'twoTables']);
    }
}
