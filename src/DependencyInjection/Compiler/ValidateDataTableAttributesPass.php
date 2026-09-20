<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\Attribute\AsDataTableResolver;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
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

        foreach (array_keys($container->findTaggedServiceIds(DataTableRegistryPass::TAG)) as $id) {
            $class = ltrim($container->getDefinition($id)->getClass() ?? $id, '\\');

            if (!class_exists($class)) {
                continue;
            }

            // Declared on the table class, so the compiled container has to follow it.
            $container->addObjectResource($class);

            $asDataTable = $this->guard($class, $class, static fn () => $resolver->resolve($class));

            $classColumns = $this->guard($class, $class, static fn () => $reader->readClassColumns($class));

            if (null === $asDataTable) {
                continue;
            }

            $container->addObjectResource($asDataTable->dataClass);

            // The table class wins the resolution chain, so the data class declarations are never
            // read. Validating them anyway would fail the build over code nothing runs.
            if ([] !== $classColumns) {
                continue;
            }

            $this->guard($class, $asDataTable->dataClass, static fn () => $reader->readColumns($asDataTable->dataClass));
        }
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
