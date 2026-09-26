<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\DependencyInjection\Compiler;

use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTableResolver;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\AttributeColumnReader;
use Pentiminax\UX\DataTables\Contracts\ActionsProvidingColumnInterface;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchableColumnInterface;
use Pentiminax\UX\DataTables\Filter\AbstractFilter;
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
            $dataColumns = $readsColumnAttributes && [] === $classColumns
                ? $this->guard($class, $dataClass, static fn () => $reader->readColumns($dataClass))
                : [];

            $dataFilters = $readsFilterAttributes && [] === $classFilters
                ? $this->guard($class, $dataClass, static fn () => $filters->readFilters($dataClass))
                : [];

            $this->assertFieldsResolveOnTheEntity(
                $container,
                $class,
                $asDataTable,
                [] !== $classColumns ? $classColumns : $dataColumns,
                [] !== $classFilters ? $classFilters : $dataFilters,
            );
        }
    }

    /**
     * A table reading its shape from a DTO still builds its DQL against the entity, so a field the
     * entity does not carry orders and searches nothing. The runtime skips it in silence; here it
     * fails the build instead, or is logged when the field only backs a fallback the column may
     * never reach.
     *
     * Only single-segment paths are checked. A dotted path may target a join alias declared in
     * customizeQueryBuilder() or through getSearchJoins(), which this pass cannot know about --
     * the same tolerance {@see \Pentiminax\UX\DataTables\Query\RelationFieldResolver::supportsSearchFiltering()}
     * applies at runtime.
     *
     * @param class-string          $dataTableClass
     * @param list<ColumnInterface> $columns
     * @param list<AbstractFilter>  $filters
     */
    private function assertFieldsResolveOnTheEntity(ContainerBuilder $container, string $dataTableClass, AsDataTable $asDataTable, array $columns, array $filters): void
    {
        $dataClass   = $asDataTable->dataClass;
        $entityClass = $asDataTable->entityClass;

        if ($dataClass === $entityClass) {
            return;
        }

        foreach ($columns as $column) {
            foreach ($this->queriedColumnFields($column) as $field => $required) {
                $this->assertFieldExists($container, $dataTableClass, $dataClass, $entityClass, (string) $field, \sprintf('column "%s"', $column->getName()), $required);
            }
        }

        foreach ($filters as $filter) {
            if ($filter->hasQueryCallback()) {
                continue;
            }

            $this->assertFieldExists($container, $dataTableClass, $dataClass, $entityClass, $filter->getField(), \sprintf('filter "%s"', $filter->getName()), true);
        }
    }

    /**
     * The field paths a column actually hands to the QueryBuilder, ordering and search alike,
     * each mapped to whether the field is required to resolve on the entity.
     *
     * A column building its own search condition names the fields it needs itself, so its
     * displayed field only backs the fallback its predicate declines. That fallback is reported
     * rather than required: {@see \Pentiminax\UX\DataTables\Query\DefaultSearchPredicateBuilder}
     * consults the predicate first and only reads the field when it returns null.
     *
     * @return array<string, bool>
     */
    private function queriedColumnFields(ColumnInterface $column): array
    {
        if ($column instanceof ActionsProvidingColumnInterface) {
            return [];
        }

        $fields = [];

        if ($column->isOrderable() && null === $column->getOrderExpression()) {
            $field = $column->getField();

            if (null !== $field && '' !== $field) {
                $fields[$field] = true;
            }
        }

        if ($column->isSearchable() || $column->isGlobalSearchable()) {
            $field = ($column instanceof SearchableColumnInterface ? $column->getSearchField() : null) ?? $column->getField();

            if (null !== $field && '' !== $field) {
                $fields[$field] ??= !self::buildsItsOwnSearchPredicate($column);
            }
        }

        return $fields;
    }

    /**
     * Whether the column builds its own search condition, through a subclass overriding
     * buildSearchPredicate() or through a setSearchPredicate() closure.
     */
    private static function buildsItsOwnSearchPredicate(ColumnInterface $column): bool
    {
        if (!$column instanceof SearchableColumnInterface) {
            return false;
        }

        if ($column instanceof AbstractColumn && $column->hasSearchPredicate()) {
            return true;
        }

        return AbstractColumn::class !== (new \ReflectionMethod($column, 'buildSearchPredicate'))->getDeclaringClass()->getName();
    }

    /**
     * @param class-string $dataTableClass
     * @param class-string $dataClass
     * @param class-string $entityClass
     */
    private function assertFieldExists(ContainerBuilder $container, string $dataTableClass, string $dataClass, string $entityClass, string $field, string $what, bool $required): void
    {
        if (str_contains($field, '.') || self::declaresProperty($entityClass, $field)) {
            return;
        }

        $message = \sprintf('the %s is ordered or searched on the field "%s", which "%s" does not declare. Name it after the entity field, redirect the query alone through the "searchField" option or an order expression, or turn ordering and searching off on it. The "field" option redirects the displayed value too, so it reads nothing on a projected row.', $what, $field, $entityClass);

        if (!$required) {
            $container->log($this, \sprintf('Invalid DataTables attribute on "%s", read by the table "%s": %s The column builds its own search condition, so this field is only read when that condition declines a term.', $dataClass, $dataTableClass, $message));

            return;
        }

        throw new InvalidArgumentException(\sprintf('Invalid DataTables attribute on "%s", read by the table "%s": %s', $dataClass, $dataTableClass, $message));
    }

    /**
     * Walks the parents explicitly: a private property declared on a mapped superclass, such as a
     * shared `$id`, is invisible to property_exists() on the entity, while Doctrine maps it.
     *
     * @param class-string $class
     */
    private static function declaresProperty(string $class, string $property): bool
    {
        for ($reflection = new \ReflectionClass($class); false !== $reflection; $reflection = $reflection->getParentClass()) {
            if ($reflection->hasProperty($property)) {
                return true;
            }
        }

        return false;
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
     * Only the hooks are read. Columns set fluently with `$table->columns()` in
     * configureDataTable() shadow the attributes too, and no reflection sees that: the table is a
     * service definition here, not an instance the pass can configure. Such a table is validated on
     * declarations it never reads. Overriding configureDataTable() is what nearly every table does,
     * so exempting it would disable these checks everywhere rather than narrow them.
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
