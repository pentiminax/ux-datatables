<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Exception\OperationNotFoundException;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Rehydrates a client-supplied row through API Platform's item pipeline.
 *
 * The row is only a set of identifiers sent back by the browser, so it carries no
 * authorization of its own. Going through the `Get` operation applies the very same
 * rules as `GET /resource/{id}`: the operation's state provider with its Doctrine
 * extensions (tenant scoping, filtered query builders) and its `security` expression.
 * Anything API Platform would not serve resolves to null.
 *
 * A resource may declare several item operations for different audiences. A row posted with
 * an `@id` is matched through the router to the operation that IRI serves, exactly as the IRI
 * converter does, and only that operation's `security` is evaluated. A row posted with a bare
 * `id` goes through the first `Get` of the resource.
 *
 * Not final: doubled in tests, like ColumnAutoDetector.
 */
class ApiPlatformItemResolver
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly ProviderInterface $provider,
        private readonly RouterInterface $router,
        private readonly ?ResourceAccessCheckerInterface $accessChecker = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return object|null the item when API Platform both serves and authorizes it, null otherwise
     */
    public function resolve(string $resourceClass, array $row): ?object
    {
        try {
            $metadataCollection = $this->resourceMetadataFactory->create($resourceClass);
        } catch (ResourceClassNotFoundException) {
            return null;
        }

        $iri = $row['@id'] ?? null;

        if (\is_string($iri) && '' !== trim($iri)) {
            $operation = $this->matchIriOperation($iri, $resourceClass, $metadataCollection);
            $item      = null === $operation ? null : $this->fetchByIri($iri);
        } else {
            $operation = $this->findFirstGetOperation($metadataCollection);
            $item      = null === $operation ? null : $this->fetchById($operation, $row['id'] ?? null);
        }

        if (null === $operation || !$item instanceof $resourceClass) {
            return null;
        }

        $expression = $operation->getSecurity();

        if (null === $expression) {
            return $item;
        }

        // Fail closed: the operation guards its items with an expression we cannot evaluate.
        if (null === $this->accessChecker) {
            return null;
        }

        $granted = $this->accessChecker->isGranted($resourceClass, $expression, [
            'object'          => $item,
            'previous_object' => null,
            'request'         => $this->requestStack?->getCurrentRequest(),
        ]);

        return $granted ? $item : null;
    }

    private function fetchByIri(string $iri): ?object
    {
        try {
            // The Symfony IriConverter routes through the operation's state provider,
            // so item extensions and scoping apply exactly as on a real GET.
            return $this->iriConverter->getResourceFromIri($iri, ['fetch_data' => true]);
        } catch (ItemNotFoundException|InvalidArgumentException) {
            return null;
        }
    }

    private function fetchById(Get $operation, mixed $id): ?object
    {
        if (!\is_scalar($id)) {
            return null;
        }

        $item = $this->provider->provide($operation, [$this->identifierName($operation) => $id]);

        return \is_object($item) ? $item : null;
    }

    /**
     * Same lookup as the IRI converter: the route matched by the IRI names the operation, so the
     * security evaluated here is the one `GET <iri>` would evaluate.
     */
    private function matchIriOperation(string $iri, string $resourceClass, ResourceMetadataCollection $metadataCollection): ?Operation
    {
        try {
            $parameters = $this->router->match($iri);
        } catch (RoutingException) {
            return null;
        }

        $operationName = $parameters['_api_operation_name'] ?? null;

        if (!\is_string($operationName) || !is_a($parameters['_api_resource_class'] ?? '', $resourceClass, true)) {
            return null;
        }

        try {
            $operation = $metadataCollection->getOperation($operationName);
        } catch (OperationNotFoundException) {
            return null;
        }

        return $operation instanceof Get ? $operation : null;
    }

    private function identifierName(Get $operation): string
    {
        $uriVariables = $operation->getUriVariables() ?? [];

        return \is_array($uriVariables) ? (array_key_first($uriVariables) ?? 'id') : 'id';
    }

    private function findFirstGetOperation(ResourceMetadataCollection $metadataCollection): ?Get
    {
        foreach ($metadataCollection as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if ($operation instanceof Get) {
                    return $operation;
                }
            }
        }

        return null;
    }
}
