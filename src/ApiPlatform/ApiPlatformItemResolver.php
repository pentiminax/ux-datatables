<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\ResourceAccessCheckerInterface;
use ApiPlatform\State\ProviderInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Rehydrates a client-supplied row through API Platform's item pipeline.
 *
 * The row is only a set of identifiers sent back by the browser, so it carries no
 * authorization of its own. Going through the `Get` operation applies the very same
 * rules as `GET /resource/{id}`: the operation's state provider with its Doctrine
 * extensions (tenant scoping, filtered query builders) and its `security` expression.
 * Anything API Platform would not serve resolves to null.
 *
 * A resource may declare several `Get` operations and the IRI converter picks its own from
 * the posted `@id`, so every `Get` security expression of the resource must grant the item:
 * over-restrictive when the operations disagree, never more permissive than the API.
 *
 * Not final: doubled in tests, like ColumnAutoDetector.
 */
class ApiPlatformItemResolver
{
    public function __construct(
        private readonly IriConverterInterface $iriConverter,
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly ProviderInterface $provider,
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
        $operations = $this->findGetOperations($resourceClass);
        $item       = $this->fetch($resourceClass, $row, $operations[0] ?? null);

        if (!$item instanceof $resourceClass) {
            return null;
        }

        foreach ($operations as $operation) {
            $expression = $operation->getSecurity();

            if (null === $expression) {
                continue;
            }

            // Fail closed: the resource guards its items with an expression we cannot evaluate.
            if (null === $this->accessChecker) {
                return null;
            }

            $granted = $this->accessChecker->isGranted($resourceClass, $expression, [
                'object'          => $item,
                'previous_object' => null,
                'request'         => $this->requestStack?->getCurrentRequest(),
            ]);

            if (!$granted) {
                return null;
            }
        }

        return $item;
    }

    /**
     * @param array<array-key, mixed> $row
     */
    private function fetch(string $resourceClass, array $row, ?Get $operation): ?object
    {
        $iri = $row['@id'] ?? null;

        if (\is_string($iri) && '' !== trim($iri)) {
            try {
                // The Symfony IriConverter routes through the operation's state provider,
                // so item extensions and scoping apply exactly as on a real GET.
                return $this->iriConverter->getResourceFromIri($iri, ['fetch_data' => true]);
            } catch (ItemNotFoundException|InvalidArgumentException) {
                return null;
            }
        }

        $id = $row['id'] ?? null;

        if (null === $operation || !\is_scalar($id)) {
            return null;
        }

        $item = $this->provider->provide($operation, [$this->identifierName($operation) => $id]);

        return \is_object($item) ? $item : null;
    }

    private function identifierName(Get $operation): string
    {
        $uriVariables = $operation->getUriVariables() ?? [];

        return \is_array($uriVariables) ? (array_key_first($uriVariables) ?? 'id') : 'id';
    }

    /**
     * @return list<Get>
     */
    private function findGetOperations(string $resourceClass): array
    {
        try {
            $metadataCollection = $this->resourceMetadataFactory->create($resourceClass);
        } catch (ResourceClassNotFoundException) {
            return [];
        }

        $operations = [];

        foreach ($metadataCollection as $resource) {
            foreach ($resource->getOperations() ?? [] as $operation) {
                if ($operation instanceof Get) {
                    $operations[] = $operation;
                }
            }
        }

        return $operations;
    }
}
