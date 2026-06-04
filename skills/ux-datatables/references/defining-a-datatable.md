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
    entityClass: User::class,        // required — enables Doctrine auto-wiring & column auto-detection
    serializationGroups: [],         // filter properties during auto-detection
    mercure: false,                  // bool | array{topics, withCredentials, debounceMs}
    apiPlatform: false,              // opt-in API Platform integration
    editModalTemplate: '',           // custom Twig template for the edit modal
    editModalAdapter: '',            // custom modal adapter
)]
```

`entityClass` is mandatory. Without it the Doctrine data provider cannot be auto-wired for server-side mode.

## The `configure*()` hooks

Override only what you need; each returns its argument fluently.

| Hook | Purpose |
|------|---------|
| `configureColumns(): iterable` | Return the column list. If omitted, columns are auto-detected from the entity (respecting `serializationGroups`). |
| `configureDataTable(DataTable $table): DataTable` | Fluent options: `serverSide()`, `processing()`, `pageLength()`, `searching()`, `ordering()`, `responsive()`, `layout()`, `language()`, etc. |
| `configureActions(Actions $actions): Actions` | Add row actions — see `references/actions.md`. |
| `configureExtensions(DataTableExtensions $extensions): DataTableExtensions` | Register extensions — see `references/extensions.md`. |

> Extensions can be added either by overriding `configureExtensions()` or fluently inside `configureDataTable()` via `$table->addExtension(...)` / `$table->responsive()` / `$table->columnControl()`.

## Data: client-side options

For client-side tables (no `serverSide()`), provide rows in one of three ways:

1. **Auto-hydration** — if `entityClass` is set and no `data()`/`ajax()`/`apiPlatform`, the bundle fetches & maps rows automatically at render time.
2. **Domain objects** — in the controller: `$table->setData($users);` runs the row-mapper pipeline (template columns, typed actions).
3. **Inline arrays** — `configureDataTable`: `$table->data([['id' => 1, 'name' => 'Alice']]);`

## Data providers

`src/DataProvider/`:

- **`DoctrineDataProvider`** — auto-wired when `entityClass` is set and `serverSide()` is on. No manual setup.
- **`ArrayDataProvider`** — for non-Doctrine / external data. Provide it by overriding:

```php
protected function createDataProvider(): ?DataProviderInterface
{
    return new ArrayDataProvider($items, $this->createRowMapper());
}
```

## Customizing the query (server-side)

Add joins / filters without touching the bundle's filtering pipeline:

```php
protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
{
    return $qb->andWhere('e.status != :s')->setParameter('s', 'archived');
}
```

The root alias is `e`. The bundle's search/order filters run *after* this hook via `QueryFilterChain`. To register custom search strategies, override `createSearchStrategyRegistry()`.

## Maker

```bash
php bin/console make:datatable
```

Scaffolds a `*DataTable` class from a Doctrine entity with auto-detected columns. `src/Maker/MakeDataTable.php`.
