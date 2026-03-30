<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\MakerBundle\MakerBundle;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\StimulusBundle\StimulusBundle;

final class MakerAppKernel extends Kernel
{
    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new StimulusBundle(), new MakerBundle(), new DataTablesBundle(), new MercureBundle()];
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'secret'               => '$ecret',
                'test'                 => true,
                'http_method_override' => false,
            ]);

            $container->loadFromExtension('twig', [
                'default_path' => __DIR__.'/templates',
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

            $container->setAlias('test.datatables.maker.datatable', 'datatables.maker.datatable')->setPublic(true);
        });
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables_maker/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables_maker/log';
    }
}
