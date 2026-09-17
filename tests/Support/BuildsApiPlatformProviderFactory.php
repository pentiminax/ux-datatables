<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Tests\Support;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\State\ProviderInterface;
use Pentiminax\UX\DataTables\ApiPlatform\ApiPlatformQueryParameterFactory;
use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\DataProvider\ApiPlatformCollectionProviderFactory;
use Pentiminax\UX\DataTables\Query\Intent\DefaultDataTableQueryIntentFactory;
use Symfony\Component\HttpFoundation\RequestStack;

trait BuildsApiPlatformProviderFactory
{
    /**
     * @param bool $withCollectionOperation whether the resource exposes the collection operation
     *                                      the provider reads from
     */
    private function buildApiPlatformProviderFactory(bool $withCollectionOperation = true): ApiPlatformCollectionProviderFactory
    {
        $resource = $withCollectionOperation
            ? (new ApiResource())->withOperations(new Operations([
                new GetCollection(uriTemplate: '/books{._format}', routePrefix: '/api'),
            ]))
            : new ApiResource();

        $metadataFactory = $this->createStub(ResourceMetadataCollectionFactoryInterface::class);
        $metadataFactory
            ->method('create')
            ->willReturnCallback(static fn (string $resourceClass): ResourceMetadataCollection => new ResourceMetadataCollection($resourceClass, [$resource]));

        $stateProvider = new class implements ProviderInterface {
            public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
            {
                return [];
            }
        };

        return new ApiPlatformCollectionProviderFactory(
            stateProvider: $stateProvider,
            collectionResolver: new ApiResourceCollectionUrlResolver($metadataFactory),
            queryParameterFactory: new ApiPlatformQueryParameterFactory(),
            intentFactory: new DefaultDataTableQueryIntentFactory(),
            columnResolver: new ColumnResolver(),
            requestStack: new RequestStack(),
        );
    }
}
