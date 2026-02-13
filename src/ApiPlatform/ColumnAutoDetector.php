<?php

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;
use Symfony\Component\TypeInfo\Type;

final class ColumnAutoDetector implements ColumnAutoDetectorInterface
{
    private const BOOLEAN_PREFIXES = ['is', 'has'];

    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameFactory,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly ApiPlatformPropertyTypeMapper $typeMapper,
        private readonly PropertyNameHumanizer $propertyNameHumanizer,
    ) {
    }

    public function supports(string $entityClass): bool
    {
        try {
            $collection = $this->resourceMetadataFactory->create($entityClass);

            foreach ($collection as $resource) {
                if ($resource instanceof ApiResource) {
                    return true;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    public function detectColumns(string $entityClass, array $groups = []): array
    {
        $context       = $groups ? ['serializer_groups' => $groups] : [];
        $propertyNames = $this->propertyNameFactory->create($entityClass, $context);

        $columns = [];

        foreach ($propertyNames as $propertyName) {
            $propertyMetadata = $this->propertyMetadataFactory->create($entityClass, $propertyName, $context);

            if (false === $propertyMetadata->isReadable()) {
                continue;
            }

            $type         = $this->resolveType($entityClass, $propertyName);
            $label        = $this->propertyNameHumanizer->humanize($propertyName);
            $isIdentifier = true === $propertyMetadata->isIdentifier();

            $column = $this->typeMapper->createColumn($propertyName, $label, $type);

            if ($isIdentifier) {
                $column->setVisible(false);
            }

            $phpPropertyName = $this->resolvePhpPropertyName($entityClass, $propertyName);

            if (null !== $phpPropertyName) {
                $column->setField($phpPropertyName);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    /**
     * Resolve the actual PHP property name when the serializer has normalized it
     * (e.g. "isActive" → "active"). Returns null when no mapping is needed.
     */
    private function resolvePhpPropertyName(string $entityClass, string $serializedName): ?string
    {
        if (property_exists($entityClass, $serializedName)) {
            return null;
        }

        foreach (self::BOOLEAN_PREFIXES as $prefix) {
            $candidate = $prefix.ucfirst($serializedName);

            if (property_exists($entityClass, $candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveType(string $entityClass, string $propertyName): ?Type
    {
        return $this->propertyInfoExtractor->getType($entityClass, $propertyName);
    }
}
