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
     * Properties first, then methods, with one continuous declaration order over both.
     *
     * @template T of object
     *
     * @param class-string    $class
     * @param class-string<T> $attributeClass
     *
     * @return list<AttributeTarget<T>>
     */
    public function readMembers(string $class, string $attributeClass): array
    {
        $properties = $this->readProperties($class, $attributeClass);
        $methods    = $this->readMethods($class, $attributeClass);

        $targets = $properties;

        foreach ($methods as $target) {
            $targets[] = new AttributeTarget(
                attribute: $target->attribute,
                name: $target->name,
                type: $target->type,
                declarationOrder: \count($targets),
            );
        }

        return $targets;
    }

    /**
     * Reads attributes declared on the class itself, for columns no member backs.
     *
     * Class-level attributes are not inherited here: the declaration belongs to the table class
     * that carries it.
     *
     * @template T of object
     *
     * @param class-string    $class
     * @param class-string<T> $attributeClass
     *
     * @return list<AttributeTarget<T>>
     */
    public function readClass(string $class, string $attributeClass): array
    {
        $targets = [];

        foreach ((new \ReflectionClass($class))->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF) as $attribute) {
            $targets[] = new AttributeTarget(
                attribute: $attribute->newInstance(),
                name: '',
                type: null,
                declarationOrder: \count($targets),
            );
        }

        return $targets;
    }

    /**
     * A getter reads as the value it exposes, so `getFullName()` names a `fullName` column.
     *
     * @template T of object
     *
     * @param class-string    $class
     * @param class-string<T> $attributeClass
     *
     * @return list<AttributeTarget<T>>
     */
    public function readMethods(string $class, string $attributeClass): array
    {
        $targets = [];

        foreach ($this->methods($class) as $method) {
            $type       = $method->getReturnType();
            $attributes = $method->getAttributes($attributeClass, \ReflectionAttribute::IS_INSTANCEOF);

            if ([] !== $attributes) {
                $this->assertReadable($method, $attributeClass);
            }

            foreach ($attributes as $attribute) {
                $targets[] = new AttributeTarget(
                    attribute: $attribute->newInstance(),
                    name: $this->propertyName($method->getName()),
                    type: $type instanceof \ReflectionNamedType ? $type : null,
                    declarationOrder: \count($targets),
                );
            }
        }

        return $targets;
    }

    /**
     * A declaration nothing can call is a programming error, and a silent one: the column would be
     * built and then have no value to show.
     *
     * @param class-string $attributeClass
     */
    private function assertReadable(\ReflectionMethod $method, string $attributeClass): void
    {
        $name = $method->getDeclaringClass()->getName().'::'.$method->getName().'()';

        if (!$method->isPublic()) {
            throw new \InvalidArgumentException(\sprintf('The "%s" attribute on "%s" needs a public method, so its value can be read.', $attributeClass, $name));
        }

        if ($method->getNumberOfRequiredParameters() > 0) {
            throw new \InvalidArgumentException(\sprintf('The "%s" attribute on "%s" needs a method callable without arguments.', $attributeClass, $name));
        }
    }

    private function propertyName(string $method): string
    {
        foreach (['get', 'is', 'has'] as $prefix) {
            if (str_starts_with($method, $prefix) && \strlen($method) > \strlen($prefix)) {
                return lcfirst(substr($method, \strlen($prefix)));
            }
        }

        return $method;
    }

    /**
     * @param class-string $class
     *
     * @return list<\ReflectionMethod>
     */
    private function methods(string $class): array
    {
        $methods = [];
        $seen    = [];

        for ($current = new \ReflectionClass($class); false !== $current; $current = $current->getParentClass()) {
            foreach ($current->getMethods() as $method) {
                $name = $method->getName();

                if ($method->getDeclaringClass()->getName() !== $current->getName() || isset($seen[$name])) {
                    continue;
                }

                $seen[$name] = true;
                $methods[]   = $method;
            }
        }

        return $methods;
    }

    /**
     * Each class contributes only what it declares itself.
     *
     * `getProperties()` on the child already reports the parent's public and protected members, so
     * taking them there and the private ones one level up would split a parent's own declarations
     * into two groups and reorder them. Columns that tie on `position` fall back to declaration
     * order, which would then be the wrong one.
     *
     * @param class-string $class
     *
     * @return list<\ReflectionProperty>
     */
    private function properties(string $class): array
    {
        $properties = [];
        $seen       = [];

        for ($current = new \ReflectionClass($class); false !== $current; $current = $current->getParentClass()) {
            foreach ($current->getProperties() as $property) {
                $name = $property->getName();

                if ($property->getDeclaringClass()->getName() !== $current->getName() || isset($seen[$name])) {
                    continue;
                }

                $seen[$name]  = true;
                $properties[] = $property;
            }
        }

        return $properties;
    }
}
