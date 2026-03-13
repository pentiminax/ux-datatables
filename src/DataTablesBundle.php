<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\ApiPlatform\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Builder\DataTableBuilder;
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Column\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\PropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\UrlColumnResolver;
use Pentiminax\UX\DataTables\Contracts\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\Contracts\ApiResourceMercureMetadataResolverInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Contracts\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormController;
use Pentiminax\UX\DataTables\Controller\AjaxEditFormSubmitController;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\Form\ColumnToFormTypeMapper;
use Pentiminax\UX\DataTables\Form\EditFormBuilder;
use Pentiminax\UX\DataTables\Form\EditFormEntityResolver;
use Pentiminax\UX\DataTables\Form\EditFormRenderer;
use Pentiminax\UX\DataTables\Form\EditFormSubmissionHandler;
use Pentiminax\UX\DataTables\Form\EditFormViewHandler;
use Pentiminax\UX\DataTables\Maker\MakeDataTable;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Routing\RouteLoader;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
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
        $this->registerCoreServices($container, $config);

        if (interface_exists(ResourceMetadataCollectionFactoryInterface::class)) {
            $this->registerApiPlatformServices($container);
        }

        if (interface_exists(\Symfony\Component\Form\FormFactoryInterface::class)) {
            $this->registerFormServices($container);
        }

        if (interface_exists(\Symfony\Component\Mercure\HubInterface::class)) {
            $this->registerMercureServices($container, $builder);
        }
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        if ($this->isAssetMapperAvailable($builder)) {
            $builder->prependExtensionConfig('framework', [
                'asset_mapper' => [
                    'paths' => [
                        __DIR__.'/../assets/dist' => '@pentiminax/ux-datatables',
                    ],
                ],
            ]);
        }
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

    private function registerCoreServices(ContainerConfigurator $container, array $config): void
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
            ->set('datatables.column.template_column_renderer', TemplateColumnRenderer::class)
            ->arg(0, service('twig')->nullOnInvalid())
            ->private();

        $container->services()
            ->set('datatables.column.action_row_data_resolver', ActionRowDataResolver::class)
            ->private();

        $container->services()
            ->alias(TemplateColumnRenderer::class, 'datatables.column.template_column_renderer')
            ->private();

        $container->services()
            ->alias(ActionRowDataResolver::class, 'datatables.column.action_row_data_resolver')
            ->private();

        $container->services()
            ->set('datatables.twig_extension', Twig\DataTablesExtension::class)
            ->arg(0, new Reference('stimulus.helper'))
            ->arg(1, service('datatables.column.template_column_renderer'))
            ->arg(2, service('datatables.column.action_row_data_resolver'))
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
            ->set('datatables.controller.ajax_delete', AjaxDeleteController::class)
            ->arg(0, service('doctrine')->nullOnInvalid())
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

        $container->services()
            ->set('datatables.column.resolver', ColumnResolver::class)
            ->arg(0, service('datatables.column.attribute_column_reader'))
            ->arg(1, service(ColumnAutoDetectorInterface::class)->nullOnInvalid())
            ->arg(2, service(UrlColumnResolver::class)->nullOnInvalid())
            ->private();

        $container->services()
            ->alias(ColumnResolver::class, 'datatables.column.resolver')
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

        $container->services()
            ->set('datatables.rendering.preparer', RenderingPreparer::class)
            ->arg(0, service(ApiResourceCollectionUrlResolverInterface::class)->nullOnInvalid())
            ->arg(1, service(MercureConfigResolverInterface::class)->nullOnInvalid())
            ->private();

        $container->services()
            ->alias(RenderingPreparer::class, 'datatables.rendering.preparer')
            ->private();

        $container->services()
            ->set('datatables.data_provider.auto_factory', AutoDataProviderFactory::class)
            ->arg(0, service('doctrine.orm.entity_manager')->nullOnInvalid())
            ->private();

        $container->services()
            ->set('datatables.data_provider.resolver', DataProviderResolver::class)
            ->arg(0, service('datatables.data_provider.auto_factory'))
            ->private();

        $container->services()
            ->set('datatables.runtime.factory', DataTableRuntimeFactory::class)
            ->arg(0, service('datatables.data_provider.resolver'))
            ->arg(1, service('datatables.column.template_column_renderer'))
            ->arg(2, service('datatables.column.action_row_data_resolver'))
            ->private();

        $container->services()
            ->alias(DataTableRuntimeFactory::class, 'datatables.runtime.factory')
            ->private();
    }

    private function registerApiPlatformServices(ContainerConfigurator $container): void
    {
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

        $container->services()
            ->set('datatables.api_platform.mercure_metadata_resolver', ApiResourceMercureMetadataResolver::class)
            ->arg(0, service('api_platform.metadata.resource.metadata_collection_factory'))
            ->private();

        $container->services()
            ->alias(ApiResourceMercureMetadataResolverInterface::class, 'datatables.api_platform.mercure_metadata_resolver')
            ->private();
    }

    private function registerFormServices(ContainerConfigurator $container): void
    {
        $container->services()
            ->set('datatables.form.column_to_form_type_mapper', ColumnToFormTypeMapper::class)
            ->private();

        $container->services()
            ->set('datatables.form.edit_form_builder', EditFormBuilder::class)
            ->arg(0, service('form.factory'))
            ->arg(1, service('datatables.form.column_to_form_type_mapper'))
            ->private();

        $container->services()
            ->set('datatables.form.edit_form_entity_resolver', EditFormEntityResolver::class)
            ->arg(0, service('doctrine')->nullOnInvalid())
            ->private();

        $container->services()
            ->set('datatables.form.edit_form_renderer', EditFormRenderer::class)
            ->arg(0, service('datatables.form.edit_form_builder'))
            ->arg(1, service('twig'))
            ->private();

        $container->services()
            ->set('datatables.form.edit_form_view_handler', EditFormViewHandler::class)
            ->arg(0, service('datatables.form.edit_form_entity_resolver'))
            ->arg(1, service('datatables.form.edit_form_renderer'))
            ->private();

        $container->services()
            ->set('datatables.form.edit_form_submission_handler', EditFormSubmissionHandler::class)
            ->arg(0, service('datatables.form.edit_form_entity_resolver'))
            ->arg(1, service('datatables.form.edit_form_renderer'))
            ->private();

        $container->services()
            ->set('datatables.controller.ajax_edit_form', AjaxEditFormController::class)
            ->arg(0, service('datatables.form.edit_form_view_handler'))
            ->tag('controller.service_arguments')
            ->public();

        $container->services()
            ->set('datatables.controller.ajax_edit_form_submit', AjaxEditFormSubmitController::class)
            ->arg(0, service('datatables.form.edit_form_submission_handler'))
            ->tag('controller.service_arguments')
            ->public();
    }

    private function registerMercureServices(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()
            ->set('datatables.mercure.hub_url_resolver', MercureHubUrlResolver::class)
            ->arg(0, service('mercure.hub.default'))
            ->private();

        $container->services()
            ->alias(MercureHubUrlResolverInterface::class, 'datatables.mercure.hub_url_resolver')
            ->private();

        $container->services()
            ->set('datatables.mercure.config_resolver', MercureConfigResolver::class)
            ->arg(0, service('datatables.mercure.hub_url_resolver'))
            ->arg(1, service('datatables.api_platform.mercure_metadata_resolver')->nullOnInvalid())
            ->private();

        $container->services()
            ->alias(MercureConfigResolverInterface::class, 'datatables.mercure.config_resolver')
            ->private();

        $container->services()
            ->set('datatables.mercure.publisher', MercureUpdatePublisher::class)
            ->arg(0, service('mercure.hub.default'))
            ->arg(1, service('logger')->nullOnInvalid())
            ->private();

        $builder->getDefinition('datatables.controller.ajax_edit')
            ->addArgument(new Reference('datatables.mercure.publisher'));

        $builder->getDefinition('datatables.controller.ajax_delete')
            ->addArgument(new Reference('datatables.mercure.publisher'));

        if ($builder->hasDefinition('datatables.form.edit_form_submission_handler')) {
            $builder->getDefinition('datatables.form.edit_form_submission_handler')
                ->addArgument(new Reference('datatables.mercure.publisher'));
        }
    }
}
