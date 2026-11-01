# Defining a DataTable

## The pattern

Extend `AbstractDataTable` (`src/Model/AbstractDataTable.php`) and annotate with `#[AsDataTable]`. The class is an autowired service — type-hint it in a controller and the bundle injects a configured instance.

```php
#[AsDataTable(User::class)]
final class UserDataTable extends AbstractDataTable { /* ... */ }
```

## `#[AsDataTable]` attribute

`src/Attribute/AsDataTable.php`:

```php
#[AsDataTable(
    dataClass: User::class,          // class carrying the column and filter attributes
    serializationGroups: [],         // filter properties during auto-detection (needs the API Platform opt-in)
    mercure: false,                  // bool | array{topics?, withCredentials, debounceMs}
    apiPlatform: false,              // opt-in API Platform integration
    editModalTemplate: null,         // custom Twig template for the edit modal
    editModalAdapter: null,          // custom modal adapter (dt, bs, bs4, bs5, or a registered name)
    entityClass: User::class,        // Doctrine entity the runtime queries and mutates
)]
```

`dataClass` is where the `#[DataTableColumn]` and `#[DataTableFilter]` declarations are read. `entityClass` is what the runtime targets: Doctrine queries, mutations, row ids, Mercure and API Platform. Each defaults to the other, so naming one class is enough; pass both only when the columns describe a DTO the entity is projected into, and add a `projectPage()` to build it. At least one is mandatory, and both must exist — a typo throws when the attribute is read. Without one the Doctrine data provider cannot be auto-wired for server-side mode. The attribute is not inherited: a subclass of an annotated abstract base resolves to no attribute at all.

When the two classes differ, a column or filter ordered or searched on a field the entity does not declare fails the build.

An array `mercure` value only declares topics when it has a `topics` key; without one, topics are auto-resolved and the other options are applied on top.

## The `configure*()` hooks

Override only what you need; each returns its argument fluently.

| Hook | Purpose |
|------|---------|
| `configureColumns(): iterable` | Return the column list. If omitted, columns are auto-detected from the entity (respecting `serializationGroups`). |
| `configureDataTable(DataTable $table): DataTable` | Fluent options and extensions: `serverSide()`, `processing()`, `pageLength()`, `searching()`, `ordering()`, `responsive()`, `buttons()`, `layout()`, `language()`, etc. |
| `configureActions(Actions $actions): Actions` | Add row actions — see `references/actions.md`. |

> Configure bundled extensions with their fluent helpers in `configureDataTable()`. Use
> `$table->addExtension(...)` for a custom extension without a dedicated helper.

## Data: client-side options

For client-side tables (no `serverSide()`), provide rows in one of three ways:

1. **Auto-hydration** — if the attribute is set and no `data()`/`ajax()`/`apiPlatform`, the bundle fetches & maps rows automatically at render time.
2. **Domain objects** — in the controller: `$table->setData($users);` runs the row-mapper pipeline (template columns, typed actions).
3. **Inline arrays** — `configureDataTable`: `$table->data([['id' => 1, 'name' => 'Alice']]);`

## Data providers

`src/DataProvider/`:

- **`DoctrineDataProvider`** — auto-wired when the attribute names a mapped `entityClass` and `serverSide()` is on. No manual setup.
- **`ArrayDataProvider`** — for non-Doctrine / external data. Provide it by overriding:

```php
protected function createDataProvider(): ?DataProviderInterface
{
    return new ArrayDataProvider($items, $this->createRowMapper());
}
```

### Refusing unsupported features

A custom provider can implement `FeatureAwareDataProviderInterface::supports(DataSourceFeature $feature): bool`
(cases `SearchBuilder`, `ColumnControl`, `ConfiguredFilters`, `Summaries`). The bundle then refuses
a request using an unsupported feature instead of letting the provider answer unfiltered data:
SearchBuilder criteria, ColumnControl searches and configured filter values get HTTP 400, and a table
declaring summaries throws a `LogicException`. Without the interface every feature is assumed
supported. `ArrayDataProvider` answers 400 for configured filter values; its 4th `$filters`
argument is deprecated since 1.3 (removed in 2.0), so drop it.

## Customizing the query (server-side)

Add joins / filters without touching the bundle's filtering pipeline:

```php
protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
{
    return $qb->andWhere('e.status != :s')->setParameter('s', 'archived');
}
```

The root alias is `e`. The bundle's search/order filters run *after* this hook via `QueryFilterPipeline`. To register custom search strategies for ColumnControl and standard column search, override `createSearchStrategyRegistry()`; to customize how global search builds a condition per column, override `createSearchPredicateBuilder()`.

## Page projection (server-side)

To enrich or map a **whole page at once** — batch-load metrics, project entities to DTOs — without an N+1, override `projectPage()`. It receives the already-paginated, hydrated page and returns the projected list:

```php
protected function projectPage(array $items): ?array
{
    return array_map(
        static fn (Customer $c): CustomerListDto => new CustomerListDto(
            $c->getId(),
            $c->getName(),
            'BADGE:'.$c->getName(),   // computed field, only on the DTO
        ),
        $items,
    );
}
```

Then a column simply reads the DTO field: `yield TextColumn::new('badge');`.

Rules and routing:

- Return `null` (the default) to disable projection. The returned list **must preserve the count and order** of `$items` — otherwise a `LogicException` is thrown.
- When a projector is active, the bundle pairs each source entity with its projected item (`RowContext`, `src/RowMapper/RowContext.php`) and routes them:
  - **Columns + TemplateColumn Twig (`row`)** read the **projected** item (the DTO).
  - **Actions, `UrlColumn`, and `setPermission()`** receive the **source** entity.
  - Without a projector, both reference the same value. Twig `source` is that original object, and Twig `payload` is the array `mapRow()` returned.
- A projected/computed column has no DB counterpart: mark it `->setOrderable(false)->setSearchable(false)`, or sort it via `->setOrderExpression(...)` backed by an `addSelect(... AS HIDDEN ...)` — see `references/server-side.md`.
- **Server-side export calls the projector per batch, not per page.** An export streams every filtered row, so `projectPage()` runs once per batch (250 rows by default for `DoctrineDataProvider`), not once over the whole result set. Project each item from itself — map it, or batch-load data keyed by it. A projector whose output depends on which other items share the call (rank, running total, share of the batch maximum) yields different values in an export than on screen. Need a larger batch? Build the provider yourself in `createDataProvider()` with a bigger `exportChunkSize`.

## Global defaults and YAML config

Implement `Pentiminax\UX\DataTables\Contracts\DataTableDefaultsConfiguratorInterface` to configure
every table in one place (autoconfigured; order with the `datatables.defaults_configurator` tag
`priority`). Layers, each overriding the previous: `data_tables` YAML, then configurators, then the
table's own `configureDataTable()`. A configurator only receives the `DataTable`; it cannot touch
Ajax routes, tokens or table-purpose validation.

```php
final class AppDataTableDefaults implements DataTableDefaultsConfiguratorInterface
{
    public function configure(DataTable $table): void
    {
        $table->stateSave(true)->pageLength(25);
    }
}
```

The `data_tables` YAML is checked when the container compiles. In 1.3 these trigger a deprecation
and become an exception in 2.0, so fix them now: an `extensions.select.style` outside `SelectStyle`,
an `extensions.buttons` entry outside `ButtonType`, and an `options.pageLength` that is not in
`options.lengthMenu`.

## Maker

```bash
php bin/console make:datatable
```

Scaffolds a `*DataTable` class from a Doctrine entity with auto-detected columns. `src/Maker/MakeDataTable.php`.
