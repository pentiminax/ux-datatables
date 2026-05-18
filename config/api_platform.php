<?php

declare(strict_types=1);

use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformPropertyTypeMapper;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolverInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceMercureMetadataResolver;
use Pentiminax\UX\DataTables\ApiPlatform\ColumnAutoDetector;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Pentiminax\UX\DataTables\Mercure\ApiResourceMercureMetadataResolverInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set('datatables.api_platform.type_mapper', ApiPlatformPropertyTypeMapper::class)
        ->private();

    $services->set('datatables.api_platform.column_auto_detector', ColumnAutoDetector::class)
        ->arg(0, service('api_platform.metadata.resource.metadata_collection_factory'))
        ->arg(1, service('api_platform.metadata.property.name_collection_factory'))
        ->arg(2, service('api_platform.metadata.property.metadata_factory'))
        ->arg(3, service('property_info'))
        ->arg(4, service('datatables.api_platform.type_mapper'))
        ->arg(5, service('datatables.column.property_name_humanizer'))
        ->private();

    $services->alias(ColumnAutoDetectorInterface::class, 'datatables.api_platform.column_auto_detector')
        ->private();

    $services->set('datatables.api_platform.collection_url_resolver', ApiResourceCollectionUrlResolver::class)
        ->arg(0, service('api_platform.metadata.resource.metadata_collection_factory'))
        ->private();

    $services->alias(ApiResourceCollectionUrlResolverInterface::class, 'datatables.api_platform.collection_url_resolver')
        ->private();

    $services->set('datatables.api_platform.mercure_metadata_resolver', ApiResourceMercureMetadataResolver::class)
        ->arg(0, service('api_platform.metadata.resource.metadata_collection_factory'))
        ->private();

    $services->alias(ApiResourceMercureMetadataResolverInterface::class, 'datatables.api_platform.mercure_metadata_resolver')
        ->private();
};
