<?php

namespace Pentiminax\UX\DataTables;

use Pentiminax\UX\DataTables\Builder\DataTableBuilder;
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilderInterface;
use Pentiminax\UX\DataTables\Maker\MakeDataTable;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class DataTablesBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('options')
                    ->children()
                        ->scalarNode('language')->defaultValue('en-GB')->end()
                        ->arrayNode('layout')
                            ->children()
                                ->scalarNode('topStart')->defaultValue('pageLength')->end()
                                ->scalarNode('topEnd')->defaultValue('search')->end()
                                ->scalarNode('bottomStart')->defaultValue('info')->end()
                                ->scalarNode('bottomEnd')->defaultValue('paging')->end()
                            ->end()
                        ->end()
                        ->arrayNode('lengthMenu')
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('pageLength')->end()
                    ->end()
                ->end()
                ->arrayNode('template_parameters')
                    ->children()
                        ->scalarNode('class')->defaultValue('table')->end()
                    ->end()
                ->end()
                ->arrayNode('extensions')
                    ->children()
                        ->arrayNode('buttons')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('select')
                            ->children()
                                ->scalarNode('style')->defaultValue('single')->end()
                             ->end()
                         ->end()
                    ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set('datatables.builder', DataTableBuilder::class)
            ->arg(0, $config['options'] ?? [])
            ->arg(1, $config['template_parameters'] ?? [])
            ->arg(2, $config['extensions'] ?? [])
            ->private();

        $container->services()
            ->alias(DataTableBuilderInterface::class, 'datatables.builder')
            ->private();

        $container->services()
            ->set('datatables.response_builder', DataTableResponseBuilder::class)
            ->private();

        $container->services()
            ->alias(DataTableResponseBuilderInterface::class, 'datatables.response_builder')
            ->private();

        $container->services()
            ->set('datatables.twig_extension', Twig\DataTablesExtension::class)
            ->arg(0, new Reference('stimulus.helper'))
            ->tag('twig.extension')
            ->private();

        $container->services()
            ->set('datatables.maker.datatable', MakeDataTable::class)
            ->arg(0, service('doctrine')->nullOnInvalid())
            ->tag('maker.command')
            ->private();
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if (!$this->isAssetMapperAvailable($builder)) {
            return;
        }

        $builder->prependExtensionConfig('framework', [
            'asset_mapper' => [
                'paths' => [
                    __DIR__.'/../assets/dist' => '@pentiminax/ux-datatables',
                ],
            ],
        ]);
    }

    private function isAssetMapperAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists(AssetMapperInterface::class)) {
            return false;
        }

        $bundlesMetadata = $builder->getParameter('kernel.bundles_metadata');
        if (!isset($bundlesMetadata['FrameworkBundle'])) {
            return false;
        }

        return is_file($bundlesMetadata['FrameworkBundle']['path'].'/Resources/config/asset_mapper.php');
    }
}
