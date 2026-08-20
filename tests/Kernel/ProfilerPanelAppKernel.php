<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Pentiminax\UX\DataTables\DataTablesBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Bundle\WebProfilerBundle\WebProfilerBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;

/**
 * Boots the bundle together with WebProfilerBundle so the collector panel
 * template can be compiled and rendered in a test.
 */
class ProfilerPanelAppKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new StimulusBundle(), new DataTablesBundle(), new MercureBundle(), new WebProfilerBundle()];
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables/profiler_panel_cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/ux_datatables/profiler_panel_log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => '$ecret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'profiler'             => ['enabled' => true, 'collect' => true],
        ]);

        $container->extension('twig', ['strict_variables' => true]);

        $container->extension('mercure', [
            'hubs' => ['default' => [
                'url' => 'http://localhost:3000/.well-known/mercure',
                'jwt' => [
                    'secret'  => 'jwt_secret',
                    'publish' => '*',
                ],
            ]],
        ]);

        $container->extension('web_profiler', [
            'toolbar'             => false,
            'intercept_redirects' => false,
        ]);

        $container->services()
            ->alias('test.datatables.profiler', 'datatables.profiler')->public();
        $container->services()
            ->alias('test.datatables.data_collector', 'datatables.data_collector')->public();
        $container->services()
            ->alias('test.twig', 'twig')->public();
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
    }
}
