<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Mercure\HubInterface;

class DataTablesBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DataTableRegistryPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('options')
                    ->children()
                        ->scalarNode('language')->defaultValue('en-GB')->end()
                        ->variableNode('layout')
                            ->defaultValue([
                                'topStart'    => 'pageLength',
                                'topEnd'      => 'search',
                                'bottomStart' => 'info',
                                'bottomEnd'   => 'paging',
                            ])
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
                ->arrayNode('edit_modal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('template')
                            ->defaultValue('@DataTables/modal/datatables/edit_modal.html.twig')->end()
                        ->scalarNode('body_template')
                            ->defaultValue('@DataTables/modal/datatables/_form_body.html.twig')->end()
                        ->scalarNode('default_title')->defaultValue('Edit')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(AbstractDataTable::class)
            ->addTag('datatables.data_table')
            ->addMethodCall('setDataTableInfrastructure', [new Reference('datatables.infrastructure')]);

        $formAvailable    = interface_exists(FormFactoryInterface::class);
        $mercureAvailable = interface_exists(HubInterface::class);

        $container->parameters()
            ->set('datatables.options', $config['options'] ?? [])
            ->set('datatables.template_parameters', $config['template_parameters'] ?? [])
            ->set('datatables.extensions', $config['extensions'] ?? [])
            ->set('datatables.edit_modal.template', $config['edit_modal']['template'])
            ->set('datatables.edit_modal.body_template', $config['edit_modal']['body_template'])
            ->set('datatables.edit_modal.default_title', $config['edit_modal']['default_title']);

        $container->import('../config/services.php');

        if ($this->isApiPlatformAvailable($builder)) {
            $container->import('../config/api_platform.php');
        }

        if ($formAvailable) {
            $container->import('../config/form.php');
        }

        if ($mercureAvailable) {
            $container->import('../config/mercure.php');
        }

        if (class_exists(AbstractMaker::class)) {
            $container->import('../config/maker.php');
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

    private function isApiPlatformAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists(ResourceMetadataCollectionFactoryInterface::class)) {
            return false;
        }

        return isset($builder->getParameter('kernel.bundles')['ApiPlatformBundle']);
    }
}
