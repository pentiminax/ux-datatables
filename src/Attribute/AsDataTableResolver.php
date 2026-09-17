<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

/**
 * The single reflection entry point for #[AsDataTable].
 *
 * The attribute is not inherited: a class carrying it gives its subclasses nothing, which is why
 * resolution only ever reads the concrete class it is handed.
 */
final class AsDataTableResolver
{
    /**
     * @var array<class-string, AsDataTable|null>
     */
    private static array $cache = [];

    /**
     * @param class-string $dataTableClass
     */
    public function resolve(string $dataTableClass): ?AsDataTable
    {
        if (\array_key_exists($dataTableClass, self::$cache)) {
            return self::$cache[$dataTableClass];
        }

        try {
            $attributes = (new \ReflectionClass($dataTableClass))->getAttributes(AsDataTable::class);
        } catch (\ReflectionException) {
            return self::$cache[$dataTableClass] = null;
        }

        return self::$cache[$dataTableClass] = [] === $attributes ? null : $attributes[0]->newInstance();
    }
}
