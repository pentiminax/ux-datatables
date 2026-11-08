<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables;

use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableDefaultsConfiguratorInterface;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\CsrfTokenManagerPass;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\DataTableRegistryPass;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\PackageLoadingControllerPass;
use Pentiminax\UX\DataTables\DependencyInjection\Compiler\ValidateDataTableAttributesPass;
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use Symfony\Bundle\MakerBundle\Maker\AbstractMaker;
use Symfony\Component\AssetMapper\AssetMapperInterface;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\Mercure\HubInterface;

class PentiminaxDataTablesBundle extends AbstractBundle
{
    protected string $extensionAlias = 'data_tables';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new DataTableRegistryPass());
        $container->addCompilerPass(new ValidateDataTableAttributesPass());
        $container->addCompilerPass(new CsrfTokenManagerPass());
        $container->addCompilerPass(new PackageLoadingControllerPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->enumNode('package_loading')
                    ->values(['builtin', 'registered'])
                    ->info('Who loads the DataTables packages: "builtin" bundles every package (1.x behavior), "registered" loads only the packages the application registers with packageLoaders. Unset follows "builtin"; the default becomes "registered" in 2.0.')
                ->end()
                ->integerNode('max_page_length')
                    ->min(1)
                    ->defaultValue(1000)
                    ->info('Upper bound applied to the DataTables "length" parameter on Ajax requests. "length=-1" (show all) is honored only when the table declares -1 in lengthMenu(); otherwise it is capped to this value.')
                ->end()
                ->arrayNode('options')
                    ->info('Defaults stamped onto every table; each key maps to the DataTables.net option of the same name.')
                    ->validate()
                        ->ifTrue(static fn (array $options): bool => self::pageLengthMissingFromMenu($options))
                        ->then(static function (array $options): array {
                            trigger_deprecation('pentiminax/ux-datatables', '1.3', 'Configuring "data_tables.options.pageLength" (%d) outside of "data_tables.options.lengthMenu" is deprecated and throws in 2.0, add it to "lengthMenu" or choose one of its entries.', $options['pageLength']);

                            return $options;
                        })
                    ->end()
                    ->children()
                        ->scalarNode('language')->info('Locale code or language file URL; unset follows the request locale.')->end()
                        ->booleanNode('stateSave')->info('Persist paging, search and ordering in the browser.')->end()
                        ->booleanNode('showHeaderResetButton')->info('Show a reset button in the header when filters are active.')->end()
                        ->variableNode('layout')
                            ->info('DataTables.net layout: maps a position (topStart, topEnd, bottomStart, bottomEnd) to a feature.')
                            ->defaultValue([
                                'topStart'    => 'pageLength',
                                'topEnd'      => 'search',
                                'bottomStart' => 'info',
                                'bottomEnd'   => 'paging',
                            ])
                        ->end()
                        ->arrayNode('lengthMenu')
                            ->info('Page sizes offered to the user; use -1 for "show all".')
                            ->scalarPrototype()->end()
                        ->end()
                        ->integerNode('pageLength')->info('Initial page size; should be one of "lengthMenu" when both are set (a mismatch is deprecated and throws in 2.0).')->end()
                        ->arrayNode('paging')
                            ->info('Pagination control options.')
                            ->children()
                                ->booleanNode('boundaryNumbers')->info('Always show the first and last page numbers.')->defaultTrue()->end()
                                ->integerNode('buttons')->info('Maximum number of pagination buttons.')->defaultValue(7)->end()
                                ->booleanNode('firstLast')->info('Show the first and last buttons.')->defaultTrue()->end()
                                ->booleanNode('numbers')->info('Show page number buttons.')->defaultTrue()->end()
                                ->booleanNode('previousNext')->info('Show the previous and next buttons.')->defaultTrue()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('table_attributes')
                    ->info('HTML attributes added to every rendered table element.')
                    ->children()
                        ->scalarNode('class')->info('CSS classes of the table element.')->defaultValue('table')->end()
                    ->end()
                ->end()
                ->arrayNode('extensions')
                    ->info('DataTables.net extensions enabled on every table.')
                    ->validate()
                        ->always(static function (array $extensions): array {
                            self::deprecateUnknownExtensionValues($extensions);

                            return $extensions;
                        })
                    ->end()
                    ->children()
                        ->arrayNode('buttons')
                            ->info('Buttons shown on every table; values outside the ButtonType enum are deprecated and throw in 2.0.')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('select')
                            ->info('Row selection; leaving it out disables the extension.')
                            ->children()
                                ->scalarNode('style')
                                    ->info('Selection style; values outside the SelectStyle enum are deprecated and throw in 2.0.')
                                    ->defaultValue(SelectStyle::SINGLE->value)
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('edit_modal')
                    ->info('Edit modal rendering.')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('template')
                            ->info('Twig template of the modal.')
                            ->defaultValue('@PentiminaxDataTables/modal/datatables/edit_modal.html.twig')->end()
                        ->scalarNode('body_template')
                            ->info('Twig template of the modal form body.')
                            ->defaultValue('@PentiminaxDataTables/modal/datatables/_form_body.html.twig')->end()
                        ->scalarNode('default_title')
                            ->info('Modal title, translated by the template in the "messages" domain; the default is translated from the "DataTables" domain.')
                            ->defaultValue('edit_modal.default_title')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->registerForAutoconfiguration(AbstractDataTable::class)
            ->addTag('datatables.data_table')
            ->addTag('kernel.reset', ['method' => 'resetDataTableState'])
            ->addMethodCall('setDataTableInfrastructure', [new Reference('datatables.infrastructure')]);

        $builder->registerForAutoconfiguration(DataTableDefaultsConfiguratorInterface::class)
            ->addTag('datatables.defaults_configurator');

        $formAvailable    = interface_exists(FormFactoryInterface::class);
        $mercureAvailable = interface_exists(HubInterface::class);

        $packageLoading = $config['package_loading'] ?? null;

        if (null === $packageLoading) {
            trigger_deprecation('pentiminax/ux-datatables', '1.5', 'Not setting "data_tables.package_loading" is deprecated: its default becomes "registered" in 2.0. Set "package_loading: registered" (or "package_loading: builtin" to keep the current behavior), or run "bin/console ux-datatables:install".');
        } else {
            // Only an explicit choice is checked against controllers.json: 1.x apps may register the controller themselves.
            $container->parameters()->set('datatables.package_loading', $packageLoading);
        }

        $container->parameters()
            ->set('datatables.controller_name', 'registered' === $packageLoading ? DataTablesExtension::CORE_CONTROLLER_NAME : DataTablesExtension::CONTROLLER_NAME)
            ->set('datatables.options', $config['options'] ?? [])
            ->set('datatables.table_attributes', $config['table_attributes'] ?? [])
            ->set('datatables.extensions', $config['extensions'] ?? [])
            ->set('datatables.edit_modal.template', $config['edit_modal']['template'])
            ->set('datatables.edit_modal.body_template', $config['edit_modal']['body_template'])
            ->set('datatables.edit_modal.default_title', $config['edit_modal']['default_title'])
            ->set('datatables.max_page_length', $config['max_page_length']);

        $container->import('../config/services.php');

        // Profiler/data-collector recording is a direct, eager call from application code
        // (not just a pull triggered by Symfony's own profiler), so it is only worth the
        // per-request cost when kernel.debug is on -- see config/profiler.php.
        if ($builder->getParameter('kernel.debug')) {
            $container->import('../config/profiler.php');
        }

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

        // Symfony 8.2 moved AssetMapper out of FrameworkBundle into its own AssetMapperBundle,
        // so FrameworkBundle no longer ships Resources/config/asset_mapper.php.
        if (isset($bundlesMetadata['AssetMapperBundle'])) {
            return true;
        }

        return isset($bundlesMetadata['FrameworkBundle'])
            && is_file($bundlesMetadata['FrameworkBundle']['path'].'/Resources/config/asset_mapper.php');
    }

    private function isApiPlatformAvailable(ContainerBuilder $builder): bool
    {
        if (!interface_exists(ResourceMetadataCollectionFactoryInterface::class)) {
            return false;
        }

        return isset($builder->getParameter('kernel.bundles')['ApiPlatformBundle']);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function pageLengthMissingFromMenu(array $options): bool
    {
        $menu = $options['lengthMenu'] ?? [];

        // Env placeholders are still strings at this point and cannot be compared.
        if (!\is_int($options['pageLength'] ?? null) || !\is_array($menu) || [] === $menu) {
            return false;
        }

        return !\in_array($options['pageLength'], $menu, false);
    }

    /**
     * @param array<string, mixed> $extensions
     */
    private static function deprecateUnknownExtensionValues(array $extensions): void
    {
        $buttons = array_column(ButtonType::cases(), 'value');
        $styles  = array_column(SelectStyle::cases(), 'value');

        $buttonValues = $extensions['buttons'] ?? [];
        foreach (\is_array($buttonValues) ? $buttonValues : [] as $button) {
            if (!self::isEnvPlaceholder($button) && !\in_array($button, $buttons, true)) {
                trigger_deprecation('pentiminax/ux-datatables', '1.3', 'The "data_tables.extensions.buttons" value %s is not a "%s" case: this is deprecated and throws in 2.0, use one of "%s".', var_export($button, true), ButtonType::class, implode('", "', $buttons));
            }
        }

        $select = $extensions['select'] ?? null;
        $style  = \is_array($select) ? ($select['style'] ?? null) : null;
        if (null !== $style && !self::isEnvPlaceholder($style) && !\in_array($style, $styles, true)) {
            trigger_deprecation('pentiminax/ux-datatables', '1.3', 'The "data_tables.extensions.select.style" value %s is not a "%s" case: this is deprecated and throws in 2.0, use one of "%s".', var_export($style, true), SelectStyle::class, implode('", "', $styles));
        }
    }

    private static function isEnvPlaceholder(mixed $value): bool
    {
        return \is_string($value) && 1 === preg_match('/env_[0-9a-f]{16}_\w+_[0-9a-f]{32}/', $value);
    }
}
