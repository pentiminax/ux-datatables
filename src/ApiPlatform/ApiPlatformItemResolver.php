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
use ApiPlatform\Metadata\UrlGeneratorInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Resolves legacy row identifiers and checks item-operation security for loaded entities.
 *
 * resolve() keeps the identifier-based behavior for callers that need API Platform's item
 * provider. isGranted() evaluates the matched `Get` operation against an entity already loaded
 * by the collection provider and performs no item fetch.
 *
 * A resource may declare several item operations for different audiences. A serialized row with
 * an `@id` is matched through the router to the operation that IRI serves, exactly as the IRI
 * converter does, and only that operation's `security` is evaluated. A row with a bare `id` goes
 * through the first `Get` of the resource.
 *
 * Not final: doubled in tests, like ColumnAutoDetector.
 */
class ApiPlatformItemResolver
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
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

        $iri = $this->resolveIri($resourceClass, $row, $metadataCollection);
        if (null === $iri) {
            return null;
        }

        $operation = $this->matchIriOperation($iri, $resourceClass, $metadataCollection);
        $item      = null === $operation ? null : $this->fetchByIri($iri);

        if (null === $operation || !$item instanceof $resourceClass) {
            return null;
        }

        return $this->isOperationGranted($resourceClass, $item, $operation) ? $item : null;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    public function isGranted(string $resourceClass, object $item, array $row): bool
    {
        if (!$item instanceof $resourceClass) {
            return false;
        }

        try {
            $metadataCollection = $this->resourceMetadataFactory->create($resourceClass);
        } catch (ResourceClassNotFoundException) {
            return false;
        }

        $iri = $this->resolveIri($resourceClass, $row, $metadataCollection);
        if (null === $iri) {
            return false;
        }

        $operation = $this->matchIriOperation($iri, $resourceClass, $metadataCollection);

        return null !== $operation && $this->isOperationGranted($resourceClass, $item, $operation);
    }

    private function isOperationGranted(string $resourceClass, object $item, Operation $operation): bool
    {
        $expression = $operation->getSecurity();

        if (null === $expression) {
            return true;
        }

        // Fail closed: the operation guards its items with an expression we cannot evaluate.
        if (null === $this->accessChecker) {
            return false;
        }

        $granted = $this->accessChecker->isGranted($resourceClass, $expression, [
            'object'          => $item,
            'previous_object' => null,
            'request'         => $this->requestStack?->getCurrentRequest(),
        ]);

        return $granted;
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

    /**
     * @param array<array-key, mixed> $row
     */
    private function resolveIri(string $resourceClass, array $row, ResourceMetadataCollection $metadataCollection): ?string
    {
        $iri = $row['@id'] ?? null;
        if (\is_string($iri) && '' !== trim($iri)) {
            return trim($iri);
        }

        $operation = $this->findFirstGetOperation($metadataCollection);
        $id        = $row['id'] ?? null;

        if (null === $operation || !\is_scalar($id)) {
            return null;
        }

        $uriVariables = $operation->getUriVariables() ?? [];
        if (1 < \count($uriVariables)) {
            return null;
        }

        $identifierName = array_key_first($uriVariables) ?? 'id';

        try {
            return $this->iriConverter->getIriFromResource(
                $resourceClass,
                UrlGeneratorInterface::ABS_PATH,
                $operation,
                ['uri_variables' => [$identifierName => $id]],
            );
        } catch (InvalidArgumentException) {
            return null;
        }
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
