<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\Attribute\AsDataTableResolver;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Filter\AttributeFilterReader;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;

/**
 * Reads every registered table's column attributes while the container compiles.
 *
 * An unknown option or a duplicated column name would otherwise surface on the first render of the
 * one table that carries it, in whichever environment happens to open that page first. Building the
 * columns here is the same work the runtime does, so nothing is validated twice in two places that
 * could drift apart.
 */
final class ValidateDataTableAttributesPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $resolver = new AsDataTableResolver();
        $reader   = new AttributeColumnReader();
        $filters  = new AttributeFilterReader();

        foreach (array_keys($container->findTaggedServiceIds(DataTableRegistryPass::TAG)) as $id) {
            $class = ltrim($container->getDefinition($id)->getClass() ?? $id, '\\');

            if (!class_exists($class)) {
                continue;
            }

            // Declared on the table class, so the compiled container has to follow it.
            $container->addObjectResource($class);

            $asDataTable = $this->guard($class, $class, static fn () => $resolver->resolve($class));

            $readsColumnAttributes = !$this->overrides($class, 'configureColumns');
            $readsFilterAttributes = !$this->overrides($class, 'configureFilters');

            $classColumns = $readsColumnAttributes
                ? $this->guard($class, $class, static fn () => $reader->readClassColumns($class))
                : [];

            $classFilters = $readsFilterAttributes
                ? $this->guard($class, $class, static fn () => $filters->readClassFilters($class))
                : [];

            if (null === $asDataTable) {
                continue;
            }

            $dataClass = $asDataTable->dataClass;

            $container->addObjectResource($dataClass);

            // The table class wins each resolution chain, so the data class declarations it shadows
            // are never read. Validating them anyway would fail the build over code nothing runs.
            if ($readsColumnAttributes && [] === $classColumns) {
                $this->guard($class, $dataClass, static fn () => $reader->readColumns($dataClass));
            }

            if ($readsFilterAttributes && [] === $classFilters) {
                $this->guard($class, $dataClass, static fn () => $filters->readFilters($dataClass));
            }
        }
    }

    /**
     * A table that builds its own columns or filters in PHP reads no attribute for them, so the
     * declarations it shadows are dead code. Failing the build over dead code would break a
     * cache:clear for something no request can reach.
     *
     * A table that overrides the hook only to return an empty collection in some branch loses its
     * build-time validation and gets the same error on the first request instead, which is where it
     * surfaced before the check existed.
     *
     * @param class-string $dataTableClass
     */
    private function overrides(string $dataTableClass, string $method): bool
    {
        return AbstractDataTable::class !== (new \ReflectionMethod($dataTableClass, $method))->getDeclaringClass()->getName();
    }

    /**
     * @template T
     *
     * @param class-string  $dataTableClass
     * @param class-string  $declaringClass
     * @param \Closure(): T $read
     *
     * @return T
     */
    private function guard(string $dataTableClass, string $declaringClass, \Closure $read): mixed
    {
        try {
            return $read();
        } catch (\InvalidArgumentException $exception) {
            $where = $declaringClass === $dataTableClass
                ? \sprintf('on "%s"', $dataTableClass)
                : \sprintf('on "%s", read by the table "%s"', $declaringClass, $dataTableClass);

            throw new InvalidArgumentException(\sprintf('Invalid DataTables attribute %s: %s', $where, $exception->getMessage()), 0, $exception);
        }
    }
}
