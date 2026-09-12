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

/**
 * Rehydrates a client-supplied row through API Platform's item pipeline.
 *
 * The row is only a set of identifiers sent back by the browser, so it carries no
 * authorization of its own. Going through the `Get` operation applies the very same
 * rules as `GET /resource/{id}`: the operation's state provider with its Doctrine
 * extensions (tenant scoping, filtered query builders) and its `security` expression.
 * Anything API Platform would not serve resolves to null.
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
    ) {
    }

    /**
     * @param array<array-key, mixed> $row
     *
     * @return object|null the item when API Platform both serves and authorizes it, null otherwise
     */
    public function resolve(string $resourceClass, array $row): ?object
    {
        $operation = $this->findGetOperation($resourceClass);
        $item      = $this->fetch($resourceClass, $row, $operation);

        if (!$item instanceof $resourceClass) {
            return null;
        }

        $expression = $operation?->getSecurity();

        if (null === $expression) {
            return $item;
        }

        // Fail closed: the resource guards its items with an expression we cannot evaluate.
        if (null === $this->accessChecker) {
            return null;
        }

        return $this->accessChecker->isGranted($resourceClass, $expression, ['object' => $item]) ? $item : null;
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

    private function findGetOperation(string $resourceClass): ?Get
    {
        try {
            $metadataCollection = $this->resourceMetadataFactory->create($resourceClass);
        } catch (ResourceClassNotFoundException) {
            return null;
        }

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
