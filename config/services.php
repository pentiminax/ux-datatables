<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\Ajax\AjaxDataTableTokenManager;
use Pentiminax\UX\DataTables\Builder\DataTableBuilder;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Column\PropertyNameHumanizer;
use Pentiminax\UX\DataTables\Column\PropertyTypeMapper;
use Pentiminax\UX\DataTables\Column\Rendering\ActionRowDataResolver;
use Pentiminax\UX\DataTables\Column\Rendering\TemplateColumnRenderer;
use Pentiminax\UX\DataTables\Column\Rendering\UrlColumnDataResolver;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Contracts\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Controller\AjaxDataController;
use Pentiminax\UX\DataTables\Controller\AjaxDeleteController;
use Pentiminax\UX\DataTables\Controller\AjaxDetailController;
use Pentiminax\UX\DataTables\Controller\AjaxEditController;
use Pentiminax\UX\DataTables\Controller\AjaxTemplateRenderController;
use Pentiminax\UX\DataTables\DataProvider\AutoDataProviderFactory;
use Pentiminax\UX\DataTables\DataProvider\DataProviderResolver;
use Pentiminax\UX\DataTables\Detail\DetailRowService;
use Pentiminax\UX\DataTables\EventListener\MutationExceptionListener;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\NullMercurePublisher;
use Pentiminax\UX\DataTables\Mutation\BooleanMutationContextResolver;
use Pentiminax\UX\DataTables\Mutation\EntityLocator;
use Pentiminax\UX\DataTables\Mutation\EntityMutator;
use Pentiminax\UX\DataTables\Query\Builder\QueryFilterPipeline;
use Pentiminax\UX\DataTables\Query\Intent\DataTableQueryIntentFactoryInterface;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Pentiminax\UX\DataTables\Rehydration\RowIdentifierExtractor;
use Pentiminax\UX\DataTables\Rehydration\SourceRowResolver;
use Pentiminax\UX\DataTables\Rendering\RenderingPreparer;
use Pentiminax\UX\DataTables\Routing\RouteLoader;
use Pentiminax\UX\DataTables\Runtime\DataTableInfrastructure;
use Pentiminax\UX\DataTables\Runtime\DataTableRuntimeFactory;
use Pentiminax\UX\DataTables\Security\MutationTokenValidator;
use Pentiminax\UX\DataTables\Security\PermissionChecker;
use Pentiminax\UX\DataTables\Twig\DataTablesExtension;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;
use Symfony\Contracts\Translation\TranslatorInterface;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('datatables.builder', DataTableBuilder::class)
        ->arg(0, param('datatables.options'))
        ->arg(1, param('datatables.template_parameters'))
        ->arg(2, param('datatables.extensions'))
        ->private();

    $services->alias(DataTableBuilderInterface::class, 'datatables.builder')
        ->private();

    $services->set('datatables.query.intent_factory', DefaultDataTableQueryIntentFactory::class)
        ->private();

    $services->alias(DataTableQueryIntentFactoryInterface::class, 'datatables.query.intent_factory')
        ->private();

    $services->set('datatables.query.filter_pipeline', QueryFilterPipeline::class)
        ->arg(0, service('datatables.query.intent_factory'))
        ->private();

    $services->alias(QueryFilterPipeline::class, 'datatables.query.filter_pipeline')
        ->private();

    $services->set('datatables.security.permission_checker', PermissionChecker::class)
        ->arg(0, service(AuthorizationCheckerInterface::class)->nullOnInvalid())
        ->private();

    $services->alias(PermissionChecker::class, 'datatables.security.permission_checker')
        ->private();

    $services->set('datatables.ajax.token_manager', AjaxDataTableTokenManager::class)
        ->arg(0, param('kernel.secret'))
        ->private();

    // Session-backed fallback when the application has no CSRF token manager.
    $services->set('datatables.security.csrf_token_manager', CsrfTokenManager::class)
        ->arg(0, inline_service(UriSafeTokenGenerator::class))
        ->arg(1, inline_service(SessionTokenStorage::class)->arg(0, service('request_stack')))
        ->private();

    $services->set('datatables.security.mutation_token_validator', MutationTokenValidator::class)
        ->arg(0, service('datatables.security.csrf_token_manager'))
        ->private();

    $services->alias(MutationTokenValidator::class, 'datatables.security.mutation_token_validator')
        ->private();

    $services->set('datatables.column.template_column_renderer', TemplateColumnRenderer::class)
        ->arg(0, service('twig')->nullOnInvalid())
        ->private();

    $services->set('datatables.column.action_row_data_resolver', ActionRowDataResolver::class)
        ->arg(0, service('datatables.security.permission_checker'))
        ->arg(1, service('property_accessor')->nullOnInvalid())
        ->private();

    $services->alias(TemplateColumnRenderer::class, 'datatables.column.template_column_renderer')
        ->private();

    $services->alias(ActionRowDataResolver::class, 'datatables.column.action_row_data_resolver')
        ->private();

    $services->set('datatables.twig_extension', DataTablesExtension::class)
        ->arg(0, service('stimulus.helper'))
        ->arg(1, service('datatables.column.template_column_renderer'))
        ->arg(2, service('datatables.column.action_row_data_resolver'))
        ->arg(3, service('datatables.column.resolver'))
        ->arg(4, service('request_stack'))
        ->arg(5, service('datatables.security.csrf_token_manager'))
        ->arg(6, service('datatables.ajax.registry')->nullOnInvalid())
        ->tag('twig.extension')
        ->private();

    $services->set('datatables.mutation.locator', EntityLocator::class)
        ->arg(0, service('doctrine')->nullOnInvalid())
        ->private();

    $services->set('datatables.mercure.null_publisher', NullMercurePublisher::class)
        ->private();

    $services->alias(MercurePublisherInterface::class, 'datatables.mercure.null_publisher')
        ->private();

    $services->set('datatables.mutation.mutator', EntityMutator::class)
        ->arg(0, service('datatables.mutation.locator'))
        ->arg(1, service('property_accessor'))
        ->arg(2, service(MercurePublisherInterface::class))
        ->arg(3, service('datatables.security.permission_checker'))
        ->arg(4, service(MercureConfigResolverInterface::class)->nullOnInvalid())
        ->arg(5, tagged_locator('datatables.data_table'))
        ->private();

    $services->set('datatables.mutation.boolean_context_resolver', BooleanMutationContextResolver::class)
        ->arg(0, service('datatables.ajax.registry'))
        ->private();

    $services->alias(BooleanMutationContextResolver::class, 'datatables.mutation.boolean_context_resolver')
        ->private();

    $services->set('datatables.event_listener.mutation_exception', MutationExceptionListener::class)
        ->tag('kernel.event_listener', ['event' => 'kernel.exception', 'priority' => 10])
        ->private();

    $services->set('datatables.controller.ajax_edit', AjaxEditController::class)
        ->arg(0, service('datatables.mutation.mutator'))
        ->arg(1, service('datatables.security.mutation_token_validator'))
        ->arg(2, service('datatables.mutation.boolean_context_resolver'))
        ->tag('controller.service_arguments')
        ->public();

    $services->set('datatables.controller.ajax_delete', AjaxDeleteController::class)
        ->arg(0, service('datatables.mutation.mutator'))
        ->arg(1, service('datatables.security.mutation_token_validator'))
        ->tag('controller.service_arguments')
        ->public();

    $services->set('datatables.controller.ajax_data', AjaxDataController::class)
        ->arg(0, service('datatables.ajax.registry'))
        ->tag('controller.service_arguments')
        ->public();

    $services->set('datatables.detail.row_service', DetailRowService::class)
        ->arg(0, tagged_locator('datatables.data_table'))
        ->arg(1, service('datatables.mutation.locator'))
        ->arg(2, service('twig')->nullOnInvalid())
        ->private();

    $services->set('datatables.controller.ajax_detail', AjaxDetailController::class)
        ->arg(0, service('datatables.detail.row_service'))
        ->tag('controller.service_arguments')
        ->public();

    $services->set('datatables.rehydration.identifier_extractor', RowIdentifierExtractor::class)
        ->private();

    $services->set('datatables.rehydration.source_row_resolver', SourceRowResolver::class)
        ->arg(0, service('datatables.rehydration.identifier_extractor'))
        ->arg(1, service('doctrine')->nullOnInvalid())
        ->private();

    $services->set('datatables.controller.ajax_templates', AjaxTemplateRenderController::class)
        ->arg(0, service('datatables.ajax.registry'))
        ->arg(1, service('datatables.runtime.factory'))
        ->arg(2, service('datatables.rehydration.source_row_resolver'))
        ->tag('controller.service_arguments')
        ->public();

    $services->set('datatables.route_loader', RouteLoader::class)
        ->tag('routing.route_loader')
        ->public();

    $services->set('datatables.column.property_name_humanizer', PropertyNameHumanizer::class)
        ->private();

    $services->set('datatables.column.property_type_mapper', PropertyTypeMapper::class)
        ->private();

    $services->set('datatables.column.attribute_column_reader', AttributeColumnReader::class)
        ->private();

    $services->alias(AttributeColumnReader::class, 'datatables.column.attribute_column_reader')
        ->private();

    $services->set('datatables.column.resolver', ColumnResolver::class)
        ->arg(0, service('datatables.column.attribute_column_reader'))
        ->arg(1, service(ColumnAutoDetectorInterface::class)->nullOnInvalid())
        ->arg(2, service('datatables.security.permission_checker'))
        ->private();

    $services->alias(ColumnResolver::class, 'datatables.column.resolver')
        ->private();

    if (interface_exists(Symfony\Component\Routing\Generator\UrlGeneratorInterface::class)) {
        $services->set('datatables.column.url_column_data_resolver', UrlColumnDataResolver::class)
            ->arg(0, service('router')->nullOnInvalid())
            ->private();

        $services->alias(UrlColumnDataResolver::class, 'datatables.column.url_column_data_resolver')
            ->private();
    }

    $services->set('datatables.rendering.preparer', RenderingPreparer::class)
        ->arg(0, service(Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolverInterface::class)->nullOnInvalid())
        ->arg(1, service(MercureConfigResolverInterface::class)->nullOnInvalid())
        ->arg(2, service(TranslatorInterface::class)->nullOnInvalid())
        ->arg(3, service(MercureHubUrlResolverInterface::class)->nullOnInvalid())
        ->arg(4, service('router')->nullOnInvalid())
        ->arg(5, service('datatables.ajax.registry'))
        ->arg(6, service('request_stack')->nullOnInvalid())
        ->private();

    $services->alias(RenderingPreparer::class, 'datatables.rendering.preparer')
        ->private();

    $services->set('datatables.data_provider.auto_factory', AutoDataProviderFactory::class)
        ->arg(0, service('doctrine.orm.entity_manager')->nullOnInvalid())
        ->private();

    $services->set('datatables.data_provider.resolver', DataProviderResolver::class)
        ->arg(0, service('datatables.data_provider.auto_factory'))
        ->private();

    $services->set('datatables.runtime.factory', DataTableRuntimeFactory::class)
        ->arg(0, service('datatables.data_provider.resolver'))
        ->arg(1, service('datatables.column.template_column_renderer'))
        ->arg(2, service('datatables.column.action_row_data_resolver'))
        ->arg(3, service(UrlColumnDataResolver::class)->nullOnInvalid())
        ->private();

    $services->alias(DataTableRuntimeFactory::class, 'datatables.runtime.factory')
        ->private();

    $services->set('datatables.infrastructure', DataTableInfrastructure::class)
        ->arg(0, service('datatables.column.resolver'))
        ->arg(1, service('datatables.rendering.preparer'))
        ->arg(2, service('datatables.runtime.factory'))
        ->arg(3, service('datatables.query.intent_factory'))
        ->arg(4, service('datatables.query.filter_pipeline'))
        ->private();

    $services->alias(DataTableInfrastructure::class, 'datatables.infrastructure')
        ->private();
};
