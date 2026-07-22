<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * Boots the bundle with the Symfony profiler enabled so the `data_collector`
 * tag is consumed and the collector service is kept in the container.
 */
class ProfilerAppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new StimulusBundle(), new DataTablesBundle(), new MercureBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container): void {
            $container->loadFromExtension('framework', [
                'secret'               => '$ecret',
                'test'                 => true,
                'http_method_override' => false,
                'profiler'             => ['enabled' => true, 'collect' => true],
            ]);

            $container->loadFromExtension('twig', [
                'default_path'     => __DIR__.'/templates',
                'strict_variables' => true,
            ]);

            $container->loadFromExtension('mercure', [
                'hubs' => ['default' => [
                    'url' => 'http://localhost:3000/.well-known/mercure',
                    'jwt' => [
                        'secret'  => 'jwt_secret',
                        'publish' => '*',
                    ],
                ]],
            ]);

            $container->setAlias('test.datatables.builder', 'datatables.builder')->setPublic(true);
            $container->setAlias('test.datatables.twig_extension', 'datatables.twig_extension')->setPublic(true);
            $container->setAlias('test.datatables.profiler', 'datatables.profiler')->setPublic(true);
            $container->setAlias('test.datatables.data_collector', 'datatables.data_collector')->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables/profiler_cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables/profiler_log';
    }
}
