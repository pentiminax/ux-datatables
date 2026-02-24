<?php

namespace Pentiminax\UX\DataTables;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\ApiPlatform\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Builder\DataTableBuilder;
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilderInterface;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\PropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\UrlColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Maker\MakeDataTable;
use Pentiminax\UX\DataTables\Routing\RouteLoader;
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

        $container->services()
            ->set('datatables.controller.ajax_edit', AjaxEditController::class)
            ->arg(0, service('doctrine')->nullOnInvalid())
            ->arg(1, service('property_accessor'))
            ->tag('controller.service_arguments')
            ->public();

        $container->services()
            ->set('datatables.route_loader', RouteLoader::class)
            ->tag('routing.route_loader')
            ->public();

        $container->services()
            ->set('datatables.column.property_name_humanizer', PropertyNameHumanizer::class)
            ->private();

        $container->services()
            ->set('datatables.column.property_type_mapper', PropertyTypeMapper::class)
            ->private();

        $container->services()
            ->set('datatables.column.attribute_column_reader', AttributeColumnReader::class)
            ->private();

        $container->services()
            ->alias(AttributeColumnReader::class, 'datatables.column.attribute_column_reader')
            ->private();

        if (interface_exists(\Symfony\Component\Routing\RouterInterface::class)) {
            $container->services()
                ->set('datatables.column.url_column_resolver', UrlColumnResolver::class)
                ->arg(0, service('router'))
                ->private();

            $container->services()
                ->alias(UrlColumnResolver::class, 'datatables.column.url_column_resolver')
                ->private();
        }

        if (interface_exists(ResourceMetadataCollectionFactoryInterface::class)) {
            $container->services()
                ->set('datatables.api_platform.type_mapper', ApiPlatformPropertyTypeMapper::class)
                ->private();

            $container->services()
                ->set('datatables.api_platform.property_name_humanizer', PropertyNameHumanizer::class)
                ->private();

            $container->services()
                ->set('datatables.api_platform.column_auto_detector', ColumnAutoDetector::class)
                ->arg(0, service('api_platform.metadata.resource.metadata_collection_factory'))
                ->arg(1, service('api_platform.metadata.property.name_collection_factory'))
                ->arg(2, service('api_platform.metadata.property.metadata_factory'))
                ->arg(3, service('property_info'))
                ->arg(4, service('datatables.api_platform.type_mapper'))
                ->arg(5, service('datatables.api_platform.property_name_humanizer'))
                ->private();

            $container->services()
                ->alias(ColumnAutoDetectorInterface::class, 'datatables.api_platform.column_auto_detector')
                ->private();

            $container->services()
                ->set('datatables.api_platform.collection_url_resolver', ApiResourceCollectionUrlResolver::class)
                ->arg(0, service('api_platform.metadata.resource.metadata_collection_factory'))
                ->private();

            $container->services()
                ->alias(ApiResourceCollectionUrlResolverInterface::class, 'datatables.api_platform.collection_url_resolver')
                ->private();
        }
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
