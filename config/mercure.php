<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\Mercure\MercureConfigResolver;
use Pentiminax\UX\DataTables\Mercure\MercureConfigResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolver;
use Pentiminax\UX\DataTables\Mercure\MercureHubUrlResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercurePublisherInterface;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolver;
use Pentiminax\UX\DataTables\Mercure\MercureTopicResolverInterface;
use Pentiminax\UX\DataTables\Mercure\MercureUpdatePublisher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('datatables.mercure.hub_url_resolver', MercureHubUrlResolver::class)
        ->arg(0, service('mercure.hub.default'))
        ->private();

    $services->alias(MercureHubUrlResolverInterface::class, 'datatables.mercure.hub_url_resolver')
        ->private();

    $services->set('datatables.mercure.config_resolver', MercureConfigResolver::class)
        ->arg(0, service('datatables.mercure.hub_url_resolver'))
        ->arg(1, service('datatables.api_platform.mercure_metadata_resolver')->nullOnInvalid())
        ->private();

    $services->alias(MercureConfigResolverInterface::class, 'datatables.mercure.config_resolver')
        ->private();

    $services->set('datatables.mercure.topic_resolver', MercureTopicResolver::class)
        ->arg(0, tagged_locator('datatables.data_table'))
        ->arg(1, service('datatables.mercure.config_resolver')->nullOnInvalid())
        ->private();

    $services->alias(MercureTopicResolverInterface::class, 'datatables.mercure.topic_resolver')
        ->private();

    $services->set('datatables.mercure.publisher', MercureUpdatePublisher::class)
        ->arg(0, service('mercure.hub.default'))
        ->arg(1, service('logger')->nullOnInvalid())
        ->private();

    $services->alias(MercurePublisherInterface::class, 'datatables.mercure.publisher')
        ->private();
};
