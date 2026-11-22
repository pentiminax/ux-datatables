<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Kernel;

use Doctrine\ORM\EntityManagerInterface;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\PentiminaxDataTablesBundle;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessActionsDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessClientSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessHydratedDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessScopedAccountsDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessServerSideDataTable;
use Pentiminax\UX\DataTables\Tests\Fixtures\DataTable\HarnessUnscopedBulkDataTable;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MercureBundle\MercureBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Symfony\UX\StimulusBundle\StimulusBundle;

class TestHarnessAppKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new TwigBundle(), new StimulusBundle(), new PentiminaxDataTablesBundle(), new MercureBundle()];
    }

    public function getCacheDir(): string
    {
        return KernelTempDir::of('ux_datatables_harness').'/cache';
    }

    public function getLogDir(): string
    {
        return KernelTempDir::of('ux_datatables_harness').'/log';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setDefinition('datatables.mercure.publisher', new Definition(NullMercurePublisher::class));
            }
        });
    }

    protected function configureContainer(ContainerConfigurator $container, LoaderInterface $loader, ContainerBuilder $builder): void
    {
        $container->extension('framework', [
            'secret'               => '$ecret',
            'test'                 => true,
            'http_method_override' => false,
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'router'               => ['utf8' => true],
        ]);

        $container->extension('mercure', [
            'hubs' => [
                'default' => [
                    'url'              => 'http://localhost:3000/.well-known/mercure',
                    'protocol_version' => '0.x',
                    'jwt'              => [
                        'secret'  => 'jwt_secret',
                        'publish' => '*',
                    ],
                ],
            ],
        ]);

        $container->extension('twig', [
            'default_path'     => __DIR__.'/templates',
            'strict_variables' => true,
        ]);

        foreach ([HarnessServerSideDataTable::class, HarnessClientSideDataTable::class, HarnessActionsDataTable::class, HarnessUnscopedBulkDataTable::class, HarnessHydratedDataTable::class, HarnessScopedAccountsDataTable::class] as $dataTableClass) {
            $builder->register($dataTableClass, $dataTableClass)
                ->setAutoconfigured(true)
                ->setPublic(true);
        }

        $builder->register('doctrine', HarnessDoctrineRegistry::class);
        $builder->register('doctrine.orm.entity_manager', EntityManagerInterface::class)->setFactory([new Reference('doctrine'), 'getManager']);

        $container->services()->alias('test.datatables.ajax_registry', 'datatables.ajax.registry')->public();

        $builder->register(HarnessController::class, HarnessController::class)
            ->setArguments([
                new Reference('twig'),
                new Reference(HarnessClientSideDataTable::class),
                new Reference(HarnessServerSideDataTable::class),
                new Reference(HarnessActionsDataTable::class),
                new Reference(HarnessHydratedDataTable::class),
            ])
            ->setPublic(true);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('datatables.route_loader::loadRoutes', 'service');

        $routes->add('harness_books', '/books')->controller([HarnessController::class, 'books']);
        $routes->add('harness_two_tables', '/two-tables')->controller([HarnessController::class, 'twoTables']);
        $routes->add('harness_actions', '/actions')->controller([HarnessController::class, 'actions']);
        $routes->add('harness_rows', '/rows')->controller([HarnessController::class, 'rows']);
        $routes->add('harness_rows_positional', '/rows-positional')->controller([HarnessController::class, 'rowsPositional']);
        $routes->add('harness_hydrated_rows', '/hydrated-rows')->controller([HarnessController::class, 'hydratedRows']);
    }
}
