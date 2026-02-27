<?php

namespace Pentiminax\UX\DataTables;

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

        return $value;
    }

    public static function readObjectValue(object $object, string $property): mixed
    {
        if (\is_callable([$object, $property])) {
            return $object->$property();
        }

        $accessor = self::buildAccessorSuffix($property);
        foreach (['get', 'is', 'has'] as $prefix) {
            $method = $prefix.$accessor;
            if (\is_callable([$object, $method])) {
                $value = $object->$method();
                if ($value instanceof \Stringable) {
                    return (string) $value;
                }

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

    private static function buildAccessorSuffix(string $property): string
    {
        if (str_contains($property, '_') || str_contains($property, '-')) {
            $property = str_replace(['-', '_'], ' ', $property);
            $property = str_replace(' ', '', ucwords($property));
        }

        return ucfirst($property);
    }
}
