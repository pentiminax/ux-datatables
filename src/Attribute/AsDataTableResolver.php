<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Attribute;

use Symfony\Contracts\Service\ResetInterface;

/**
 * The single reflection entry point for #[AsDataTable].
 *
 * The attribute is not inherited: a class carrying it gives its subclasses nothing, which is why
 * resolution only ever reads the concrete class it is handed. Callers hand it a class-string of a
 * registered table, so a class that cannot be reflected is a programming error and is left to
 * throw rather than reported as "no attribute".
 *
 * The cache belongs to the instance, not the class: a worker runtime keeps the same container
 * across requests, and a cache nothing can clear would outlive the request that filled it.
 */
final class AsDataTableResolver implements ResetInterface
{
    /**
     * @var array<class-string, AsDataTable|null>
     */
    private array $cache = [];

    /**
     * @param class-string $dataTableClass
     */
    public function resolve(string $dataTableClass): ?AsDataTable
    {
        if (\array_key_exists($dataTableClass, $this->cache)) {
            return $this->cache[$dataTableClass];
        }

        $attributes = (new \ReflectionClass($dataTableClass))->getAttributes(AsDataTable::class);

        return $this->cache[$dataTableClass] = [] === $attributes ? null : $attributes[0]->newInstance();
    }

    public function reset(): void
    {
        $this->cache = [];
    }
}
