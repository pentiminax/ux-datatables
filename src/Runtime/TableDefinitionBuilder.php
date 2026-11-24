<?php

declare(strict_types=1);

namespace Pentiminax\UX\DataTables\Runtime;

use Pentiminax\UX\DataTables\ApiPlatform\ApiResourceCollectionUrlResolver;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Attribute\AsDataTableResolver;
use Pentiminax\UX\DataTables\Column\AbstractColumn;
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Column\ColumnResolver;
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Filter\AbstractFilter;
use Pentiminax\UX\DataTables\Filter\AttributeFilterReader;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\Actions;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;
use Pentiminax\UX\DataTables\Model\Filters;

/**
 * Calls a table's configure*() methods once and keeps the result as a {@see TableDefinition}.
 *
 * It holds only services that are a function of the code and the bundle configuration: nothing here
 * reads the request, the security token, the session, or the locale, so the definition cannot
 * depend on them either. A table is configured once per instance, which under a worker runtime is
 * once per worker.
 *
 * @internal
 */
final class TableDefinitionBuilder
{
    /** @var \WeakMap<AbstractDataTable, TableDefinition> */
    private \WeakMap $definitions;

    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $attributes
     * @param array<string, mixed> $extensions
     * @param iterable<object>     $defaultsConfigurators
     */
    public function __construct(
        private readonly ColumnResolver $columnResolver,
        private readonly array $options = [],
        private readonly array $attributes = [],
        private readonly array $extensions = [],
        private readonly AsDataTableResolver $asDataTableResolver = new AsDataTableResolver(),
        private readonly AttributeFilterReader $attributeFilterReader = new AttributeFilterReader(),
        private readonly iterable $defaultsConfigurators = [],
        private readonly ?ApiResourceCollectionUrlResolver $collectionUrlResolver = null,
    ) {
        $this->definitions = new \WeakMap();
    }

    public function build(AbstractDataTable $table): TableDefinition
    {
        return $this->definitions[$table] ??= $this->configure($table);
    }

    /**
     * The definition reading its rows from rows the row pipeline already mapped.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @throws \LogicException when the table combines inline rows with an API Platform opt-in
     */
    public function withInlineRows(TableDefinition $definition, array $rows): TableDefinition
    {
        $table = clone $definition->table;
        $table->data($rows);

        return new TableDefinition(
            class: $definition->class,
            table: $table,
            columns: $definition->columns,
            filters: $definition->filters,
            asDataTable: $definition->asDataTable,
            optIns: ResolvedOptIns::resolve($table, $definition->asDataTable, $this->collectionUrlResolver),
            requirements: $definition->requirements,
        );
    }

    private function configure(AbstractDataTable $table): TableDefinition
    {
        $class       = $table::class;
        $asDataTable = $this->asDataTableResolver->resolve($class);

        $dataTable          = $this->createDataTable($this->shortName($class));
        $defaultSelect      = $dataTable->getExtensionsCollection()->getSelectExtension();
        $defaultSelectState = $defaultSelect?->jsonSerialize();

        $configured = null;
        $optIns     = null;

        $context = new TableContext(
            defaultColumns: function () use (&$configured, &$optIns, $asDataTable, $class): iterable {
                $columns = $configured?->getColumns() ?? [];

                return [] !== $columns ? $columns : $this->columnResolver->resolveColumns($asDataTable, dataTableClass: $class, optIns: $optIns);
            },
        );

        [$columns, $filters] = $table->configureWith($context, function (AbstractDataTable $hooks) use ($dataTable, $class, $asDataTable, $defaultSelect, $defaultSelectState, &$configured, &$optIns): array {
            $configured = $hooks->configureDataTable($dataTable);
            $configured->setDataTableClass($class);

            $optIns  = ResolvedOptIns::resolve($configured, $asDataTable, $this->collectionUrlResolver);
            $columns = iterator_to_array($hooks->configureColumns());

            $actions = $hooks->configureActions(new Actions());

            $this->columnResolver->configureActionEntityClass($actions, $asDataTable);

            // Permission filtering is request-scoped and happens at the serialization and query
            // boundaries; applying it here would give every user the first user's columns.
            $columns = $this->withActionColumns($columns, $actions);

            $configured->columns($columns);

            $filters = $hooks->configureFilters(new Filters());

            if ($filters->isEmpty()) {
                foreach ($this->filtersFromAttributes($class, $asDataTable) as $filter) {
                    $filters->add($filter);
                }
            }

            $configured->setFilters($filters);

            $bulkActions = $hooks->configureBulkActions(new BulkActions());

            if (!$bulkActions->isEmpty()) {
                $this->enableSelectionForBulkActions($class, $configured, $defaultSelect, $defaultSelectState);
            }

            $configured->setBulkActions($bulkActions);

            return [$columns, $filters];
        });

        return new TableDefinition($class, $configured, $columns, $filters, $asDataTable, $optIns, TableRequirements::of($configured));
    }

    /**
     * Stamps the bundle-wide `data_tables` defaults, then the global defaults configurators, onto
     * the table. The table's own configureDataTable() runs afterwards and wins.
     */
    private function createDataTable(string $id): DataTable
    {
        $table = new DataTable($id, $this->options, $this->attributes, $this->extensions);

        foreach ($this->defaultsConfigurators as $configurator) {
            $configurator->configure($table);
        }

        return $table;
    }

    /**
     * Filters declared through #[DataTableFilter], read down the same chain the columns use: the
     * table class first, then the data class. Only reached when configureFilters() declared nothing.
     *
     * @param class-string<AbstractDataTable> $class
     *
     * @return list<AbstractFilter>
     */
    private function filtersFromAttributes(string $class, ?AsDataTable $asDataTable): array
    {
        $filters = $this->attributeFilterReader->readClassFilters($class);

        if ([] !== $filters) {
            return $filters;
        }

        return null === $asDataTable ? [] : $this->attributeFilterReader->readFilters($asDataTable->dataClass);
    }

    /**
     * @param array<array-key, AbstractColumn> $columns
     *
     * @return array<array-key, AbstractColumn>
     */
    private function withActionColumns(array $columns, Actions $actions): array
    {
        if ($actions->isEmpty()) {
            return $columns;
        }

        $groups = $actions->partitionByPosition();
        $single = 1 === \count($groups);

        foreach ($groups as $position => $group) {
            $isBefore = ActionsPosition::BeforeColumns->value === $position;

            $name = $single || !$isBefore ? 'actions' : 'actions_before';

            $actionColumn = ActionColumn::fromActions($name, $group->getColumnLabel(), $group);

            if ($isBefore) {
                array_unshift($columns, $actionColumn);

                continue;
            }

            $columns[] = $actionColumn;
        }

        return $columns;
    }

    /**
     * Bulk actions need checkboxes and multi-row selection, so a table that declares them gets the
     * Select extension configured for that. A table that declares Select itself keeps its own
     * configuration, except that single-row selection is refused outright: it can never produce a
     * batch.
     *
     * The bundle-wide `data_tables.extensions.select` default is not a choice the table made, so it
     * is replaced rather than refused: its `single` default would otherwise break every table that
     * declares bulk actions. The default only counts as untouched while configureDataTable() left
     * it as it was seeded; a table that changed it in place made the choice its own.
     *
     * @param array<string, mixed>|null $defaultSelectState the default as seeded, before configureDataTable()
     */
    private function enableSelectionForBulkActions(string $class, DataTable $table, ?SelectExtension $defaultSelect, ?array $defaultSelectState): void
    {
        $select = $table->getExtensionsCollection()->getSelectExtension();

        $untouchedDefault = null !== $select
            && $select                  === $defaultSelect
            && $select->jsonSerialize() === $defaultSelectState;

        if (null === $select || $untouchedDefault) {
            $table->useSelectionForBulkActions(
                (new SelectExtension(style: SelectStyle::MULTI))->withCheckbox()->headerCheckbox()
            );

            return;
        }

        if (SelectStyle::MULTI !== $select->getStyle()) {
            throw new \LogicException(\sprintf('Table "%s" declares bulk actions, which need SelectStyle::MULTI, but its Select extension uses SelectStyle::%s.', $class, $select->getStyle()->name));
        }
    }

    private function shortName(string $class): string
    {
        $pos = strrpos($class, '\\');

        return false === $pos ? $class : substr($class, $pos + 1);
    }
}
