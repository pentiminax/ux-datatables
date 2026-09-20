<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * Collects attributes declared on the members of a class and of the classes it inherits from.
 *
 * Reflection only reports a private property on the class that declares it, so a Doctrine mapped
 * superclass holding private members would otherwise lose everything it annotates. The chain is
 * walked explicitly, and a child redeclaring a name wins over its parent.
 */
final class MemberAttributeReader
{
    /**
     * @template T of object
     *
     * @param class-string    $class
     * @param class-string<T> $attributeClass
     *
     * @return list<AttributeTarget<T>>
     */
    public function readProperties(string $class, string $attributeClass): array
    {
        $targets = [];

        foreach ($this->properties($class) as $property) {
            $type = $property->getType();

            foreach ($property->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
                $targets[] = new AttributeTarget(
                    attribute: $attribute->newInstance(),
                    name: $property->getName(),
                    type: $type instanceof \ReflectionNamedType ? $type : null,
                    declarationOrder: \count($targets),
                );
            }
        }

        return $targets;
    }

    /**
     * @param class-string $class
     *
     * @return list<\ReflectionProperty>
     */
    private function properties(string $class): array
    {
        $properties = [];

        for ($current = new \ReflectionClass($class); false !== $current; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                $properties[$property->getName()] ??= $property;
            }
        }

        return array_values($properties);
    }
}
