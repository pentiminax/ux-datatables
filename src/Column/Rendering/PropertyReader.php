<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Column\Rendering;

final class PropertyReader
{
    public static function readPath(mixed $value, string $path): mixed
    {
        if ('' === $path) {
            return null;
        }

        foreach (explode('.', $path) as $segment) {
            if (\is_array($value)) {
                if (!isset($value[$segment])) {
                    return null;
                }

                $value = $value[$segment];
                continue;
            }

            if (\is_object($value)) {
                $value = self::readObjectValue($value, $segment);
                continue;
            }

            return null;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        return $value;
    }

    public static function readObjectValue(object $object, string $property): mixed
    {
        // Bare `score()`-style readers and get/is/has accessors must take no required
        // arguments: `hasRole(string $role)` is common on entities, and calling it while
        // mapping a `role` column threw ArgumentCountError for every row. `method_exists`
        // also keeps `__call` from shadowing a public property of the same name.
        if (self::tryCallZeroArgMethod($object, $property, $value)) {
            return $value;
        }

        $accessor = self::buildAccessorSuffix($property);
        foreach (['get', 'is', 'has'] as $prefix) {
            if (self::tryCallZeroArgMethod($object, $prefix.$accessor, $value)) {
                return $value;
            }
        }

        if (property_exists($object, $property)) {
            $reflection = new \ReflectionObject($object);
            if (!$reflection->hasProperty($property) || $reflection->getProperty($property)->isPublic()) {
                return $object->$property;
            }
        }

        return null;
    }

    private static function tryCallZeroArgMethod(object $object, string $method, mixed &$value): bool
    {
        if (!method_exists($object, $method)) {
            return false;
        }

        $reflection = new \ReflectionMethod($object, $method);
        if (!$reflection->isPublic() || $reflection->getNumberOfRequiredParameters() > 0) {
            return false;
        }

        $value = $object->$method();

        return true;
    }

    private static function buildAccessorSuffix(string $property): string
    {
        if (str_contains($property, '_') || str_contains($property, '-')) {
            $property = str_replace(['-', '_'], ' ', $property);
            $property = str_replace(' ', '', ucwords($property));
        }

        return ucfirst($property);
    }
}
