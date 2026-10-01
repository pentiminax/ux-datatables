<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class RouteLoaderAppKernel extends TwigAppKernel
{
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        parent::registerContainerConfiguration($loader);
        $loader->load(static function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'router' => ['resource' => __DIR__.'/../../config/routes.php'],
            ]);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables/route_loader_cache/'.$this->environment;
    }
}
