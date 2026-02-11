<?php

namespace Pentiminax\UX\DataTables\ApiPlatform;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Property\Factory\PropertyMetadataFactoryInterface;
use ApiPlatform\Metadata\Property\Factory\PropertyNameCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnAutoDetectorInterface;
use Symfony\Component\PropertyInfo\PropertyInfoExtractorInterface;

final class ColumnAutoDetector implements ColumnAutoDetectorInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $resourceMetadataFactory,
        private readonly PropertyNameCollectionFactoryInterface $propertyNameFactory,
        private readonly PropertyMetadataFactoryInterface $propertyMetadataFactory,
        private readonly PropertyInfoExtractorInterface $propertyInfoExtractor,
        private readonly ApiPlatformPropertyTypeMapper $typeMapper,
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
        $context = [] !== $groups ? ['serializer_groups' => $groups] : [];
        $propertyNames = $this->propertyNameFactory->create($entityClass, $context);

        $columns = [];

        foreach ($propertyNames as $propertyName) {
            $propertyMetadata = $this->propertyMetadataFactory->create($entityClass, $propertyName, $context);

            if (false === $propertyMetadata->isReadable()) {
                continue;
            }

            $type = $this->resolveType($entityClass, $propertyName);
            $label = self::humanize($propertyName);
            $isIdentifier = true === $propertyMetadata->isIdentifier();

            $column = $this->typeMapper->createColumn($propertyName, $label, $type);

            if ($isIdentifier) {
                $column->setVisible(false);
            }

            $columns[] = $column;
        }

        return $columns;
    }

    private function resolveType(string $entityClass, string $propertyName): mixed
    {
        if (method_exists($this->propertyInfoExtractor, 'getType')) {
            $type = $this->propertyInfoExtractor->getType($entityClass, $propertyName);
            if (null !== $type) {
                return $type;
            }
        }

        /** @phpstan-ignore method.deprecated */
        $types = $this->propertyInfoExtractor->getTypes($entityClass, $propertyName);

        return $types[0] ?? null;
    }

    /**
     * Convert a camelCase or snake_case property name into a human-readable label.
     */
    public static function humanize(string $name): string
    {
        $label = str_replace(['_', '-'], ' ', $name);
        $label = preg_replace('/(?<!^)([A-Z])/', ' $1', $label);
        $label = trim($label ?? '');
        $label = ucwords($label);
        $label = preg_replace('/\bId\b/', 'ID', $label);

        return '' === $label ? $name : $label;
    }
}
