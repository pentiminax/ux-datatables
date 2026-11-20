# Upgrade Guide

This guide is for humans. If you are an AI agent, read [UPGRADE-AI.md](UPGRADE-AI.md) instead.

This guide lists what you may have to change in your application when you upgrade. It covers
behavior changes, breaking changes, and deprecations. New features are not listed here: see the
[release notes](https://github.com/pentiminax/ux-datatables/releases).

Sections are ordered from newest to oldest. When you skip versions, read every section between
your current version (excluded) and the target version (included).

The guide starts at **1.1.0**, the first upgrade after the stable 1.0 release. To upgrade from a
0.x version, read the [guide as published in 1.4.0](https://github.com/pentiminax/ux-datatables/blob/v1.4.0/UPGRADE.md).

Deprecated APIs keep working for the whole 1.x line and are removed in 2.0.

## Upgrading to 2.0.0

### The frontend is installed with `ux-datatables:install`

2.0 removes the built-in package loaders. The bundle no longer ships the DataTables.net packages
for every style: your application owns the package loaders for its kit and extensions, which
`ux-datatables:install` copies into `assets/datatables.js`. Run it once after `composer update`:

```bash
php bin/console ux-datatables:install --style=tailwind buttons responsive select
```

- **Migration.** An application on 1.6 with registered package loading keeps its setup: the
  command recognizes the files the 1.5 and 1.6 installer wrote and updates them in place. An
  application on built-in loading gets a working registered setup from the same command; pass the
  extensions it uses, or let the command detect them from your `AbstractDataTable` classes. A file
  you edited is shown as a diff and replaced only after you confirm. A non-interactive run never
  overwrites it.
- **Kits.** `dt`, `bs5` and Tailwind (`--style=tailwind`, which is `dt` plus the bundled Tailwind
  theme). The `bs5` kit also copies the Bootstrap modal adapter to
  `assets/datatables_bootstrap_modal.js`.
- **`datatable` is the core controller.** It imports only `@hotwired/stimulus`, and the base style,
  the `dt` CSS and the Tailwind theme are autoimported. `datatable_core` stays as a deprecated alias
  until 3.0; the install command leaves its `controllers.json` entry as it is. The Stimulus identifier is `pentiminax--ux-datatables--datatable`; code that looks up
  `pentiminax--ux-datatables--datatable-core` keeps working only while the alias is enabled. To
  drop the Tailwind theme, set its `autoimport` flag to `false`.
- **`package_loading` is removed.** `registered` is accepted, ignored and deprecated, so remove it
  from `data_tables.yaml` (the install command does). `builtin` fails with a message pointing to
  the install command.
- **Main entry.** `@pentiminax/ux-datatables` is now the core entry: it exports the controller, the
  registries (`packageLoaders`, `buttonActions`, `modalAdapters`, `columnStyleAdapters`) and the
  adapter types, and no longer imports DataTables packages. `@pentiminax/ux-datatables/dist/controller.js`,
  `dist/index.js` and `BootstrapModalAdapter` are removed: the Bootstrap adapter is the copied file.
- **Peer dependencies.** Only `@hotwired/stimulus` is required; the DataTables packages are optional.
  Symfony Flex no longer adds import map entries for packages you do not use, so you can delete the
  ones the 1.x recipe added and the install command did not require.
- **PHP symbols.** `DataTablesExtension::CORE_CONTROLLER_NAME` and `CORE_VIEW_ATTRIBUTE` and the
  `$controllerName` constructor argument are removed, as is the `datatables.controller_name`
  container parameter: the extension always renders `DataTablesExtension::CONTROLLER_NAME` and
  `VIEW_ATTRIBUTE`. Read the table view with `DataTablesExtension::VIEW_ATTRIBUTE`, or with
  `DataTableView` in tests.
- **`--prune` is removed** from `ux-datatables:install`: remove it from scripts and Composer hooks.
- **Styles.** The style comes from `data_tables.style_framework` (unset, it is the style of the registered loaders; several registered styles throw) or
  `styleFramework()` on a table, never from the page stylesheets: autodetection is removed. A style with no registered
  adapter fails with a message naming the missing registration. Two styles can coexist by
  registering a loader per framework: `packageLoaders.register('dataTables', loader, 'bs5')`. Requesting a
  style that has only other styles' loaders fails with a message naming that third argument.

Copied code is covered by the 2.x backward compatibility promise: the loader signature, the
registries, and the modal and column style adapter interfaces change only in a major version.

### Bootstrap 3 and 4 are removed

`StyleFramework::Bootstrap` and `StyleFramework::Bootstrap4`, the `bs` and `bs4` frontend styles
and their packages are removed. Use `StyleFramework::Bootstrap5` (`bs5`) or
`StyleFramework::DataTables` (`dt`). A bundle config setting `style_framework` to `bs` or `bs4`
fails with a message naming `bs5` and `dt`.

### Rendering no longer writes to the table

`render_datatable()` builds the payload it sends to the browser without writing to your table.
`DataTableRuntime::definition($table)->table` returns the table exactly as `configureDataTable()` left it, before and
after a render: it no longer carries the bundle's Ajax URL, the `serverSide` flag of an entity
table, the hydrated rows, the resolved Mercure configuration or the translated labels.

- `AbstractDataTable::getDataTable()` and `prepareForRendering()` are removed. Read the table with
  `DataTableRuntime::definition()` and render it with `render_datatable()`. A subclass override of either
  method is no longer called.

  ```php
  // Before (1.x)
  $ajax = $table->getDataTable()->getOption('ajax');

  // After (2.0): the rendered value, read from the page in a test extending DataTableTestCase
  $ajax = $this->dataTableView($crawler, MyTable::class)->option('ajax');
  ```

  To check what a table renders, render it and read the payload with the test helper
  (`DataTableTestCase::dataTableView()` on the crawled page).
- `DataTable::getOption()` and `getOptions()` return DataTables.net options only, as announced in
  1.7. Bundle keys (`apiPlatform`, `pollInterval`, `urlState`, `styleFramework`, `exportUrl`,
  `showHeaderResetButton`, `mercure`, `bulkActions`, `filters`, ...) are no longer part of the option
  bag: read your own configuration from the method that set it (`getMercureConfig()`,
  `getBulkActions()`, `getFilters()`), and the rendered value from the payload.
- The data source, API Platform, Mercure and edit modal opt-ins are resolved once, when the table is
  configured. Change them in `configureDataTable()` or `#[AsDataTable]`: a change made later through
  `getConfiguredDataTable()` is not picked up.
- `MercureUpdatePublisher::publishForDataTable()` takes the `AbstractDataTable` only:
  `publishForDataTable($table, $data)` instead of `publishForDataTable($table->getDataTable(), $data)`.
- `DataTable::apiPlatformServerSide()` is removed. The bundle still sends the flag when a table
  needs it: remove the call.
- `RenderingPreparer`, `FilterEntityOptionsResolver` and `SearchListOptionsResolver` are now
  `@internal` and their signatures changed. Do not call them from application code.
  `RenderingPreparer::prepare()` is removed. `prepareBeforeDataHydration()` and
  `prepareAfterDataHydration()` lose the `?AsDataTable` parameter, and `prepareAfterDataHydration()`
  returns a `PreparedDataTable`. `RenderingPreparer::resolveMercureConfig()` and its Mercure
  constructor arguments are removed: use `DataTableRuntime::mercureConfig($table)`. `FilterEntityOptionsResolver::prepare()`
  takes the `Filters`. `SearchListOptionsResolver::prepare()` is removed.

### The table is a definition, served by `DataTableRuntime`

`AbstractDataTable` now only declares: the bundle calls the `configure*()` methods once, keeps the
result as an immutable definition, and serves render, Ajax data, export, row actions and bulk
actions through the `DataTableRuntime` service. The table holds no request, runtime or
infrastructure state, so the infrastructure setter, the initialization flags and the runtime
accessors are gone. A definition never reads the request, the security token, the session or the
locale: keep reading them in `customizeQueryBuilder()`, `permission()` and the other request-scoped
hooks.

- **Hooks stay `protected`.** `mapRow()`, `projectPage()`, `customizeQueryBuilder()`,
  `createDataProvider()`, `createSearchStrategyRegistry()` and `createSearchPredicateBuilder()` keep
  their 1.x signatures and visibility, and `AbstractDataTable` does not implement
  `DataTableHooksInterface`: the runtime reaches them through an internal bound object.
  `getHttpRequest()`, `getCurrentRequest()`, `getResolvedColumns()` and `createRowMapper()` keep
  working inside these hooks. Outside a call the bundle makes, the first two return `null` and the
  other two throw a `LogicException`.
- **The definition is built once per table instance.** Under a worker runtime that is once for the
  worker's lifetime, no longer on every request. A `configure*()` method, or a
  `DataTableDefaultsConfiguratorInterface` service that configures your tables, must not read the
  user, the request, the session or the locale: such a value would be frozen for every later
  request. Move that logic to `permission()` or `customizeQueryBuilder()`. State a `configure*()`
  method stores on `$this` still reaches the hooks.
- **`configureQueryBuilder()` is removed without a 1.x deprecation.** It was `final protected`, so an
  override already failed to load; a call to it no longer exists. The bundle applies search,
  ordering and filters after `customizeQueryBuilder()`: put your query changes there.
- **Custom Ajax endpoints use `DataTableResponder::respond()`**, as announced in 1.7. The
  `handleRequest()`, `isRequestHandled()`, `getResponse()`, `getRequest()`, `fetchData()` and
  `getDataProvider()` methods of `AbstractDataTable` are removed, and a subclass override of them is
  no longer called.

  ```php
  // Before (1.7)
  $table->handleRequest($request);

  if ($table->isRequestHandled()) {
      return $table->getResponse();
  }

  // After (2.0)
  if ($response = $responder->respond($table, $request)) {
      return $response;
  }
  ```
- **Read the configuration from the runtime.** `getConfiguredDataTable()`, `getEntityClass()`,
  `getColumnByName()`, `getResolvedOptIns()`, `prepareRendering()` and
  `resolveMercureConfigWithoutHydration()` are removed from the table. Inject
  `Pentiminax\UX\DataTables\Runtime\DataTableRuntime` and call `definition($table)` (its `table`
  property is what `getConfiguredDataTable()` returned, `entityClass()` and `optIns` replace the
  others), `render($table)` or `mercureConfig($table)`.

  ```php
  // Before (1.x)
  $columns = $table->getConfiguredDataTable()->getColumns();

  // After (2.0)
  $columns = $runtime->definition($table)->table->getColumns();
  ```
- **Inline rows are mapped when the table renders.** A mapping error surfaces inside
  `render_datatable()`, wrapped by Twig. The rendered payload is unchanged. See
  "Inline rows are passed to `render_datatable()`".
- **The `kernel.reset` tag and `resetDataTableState()` are gone.** A table holds no state, so the
  bundle no longer tags tables with `kernel.reset`. Remove the tag from tables you register by hand,
  and the `resetDataTableState()` calls from your tests.
- **Services that were built by hand.** `DataTableInfrastructure`, `DataTableRuntimeFactory` and the
  `datatables.infrastructure` and `datatables.runtime.factory` services are removed; `datatables.runtime`
  and `datatables.definition_builder` replace them. `DataTablesExtension` only declares
  `render_datatable()` and has no constructor: the rendering moved to `DataTablesRuntime` (Stimulus
  helper, authorization checker, `DataTableRuntime`, profiler). `DataTableResponder`,
  `AjaxDataTableRegistry`, `SecurityVoter`, `RowScopeGuard`, `BulkActionRunner`, `ExportService`,
  `MercureUpdatePublisher`, `MercureTopicResolver`, `EditModalTemplateResolver`, `ExtensionDetector`
  and `InstallCommand` take the runtime or the definition builder. `AutoDataProviderFactory::create()`
  takes the definition and the hooks instead of closures, and `ResolvedDataTable` carries the
  definition. Application code should not build them: the container does.

  If you do build them, the argument positions (1-based) are: `RowScopeGuard($locator, $runtime,
  $requestStack)`, so the runtime is argument 2, before the request stack; `ExportService($exporters,
  $columnResolver, $runtime, $translator)`, argument 3, before the translator;
  `MercureUpdatePublisher($hub, $runtime, $logger)`, argument 2, before the logger;
  `MercureTopicResolver($runtime, $configResolver, $dataTables, $logger)`, a required argument 1 that
  shifts the others by one; `EditModalTemplateResolver($dataTables, $defaultTemplate,
  $defaultBodyTemplate, $runtime, $asDataTableResolver)`, argument 4, so `AsDataTableResolver` moves
  to 5; `ExtensionDetector($tables, $runtime)`, argument 2. Appended at the end: `BulkActionRunner`
  takes the scope guard as argument 6 and the runtime as 7, `AjaxDataTableRegistry` the definition
  builder as argument 5, `InstallCommand` the optional runtime as argument 3. `SecurityVoter`
  takes the definition builder as argument 2. `DataTableResponder` takes the runtime as its only
  argument.

### Inline rows are passed to `render_datatable()`

`AbstractDataTable::rows()`, `getInlineRows()` and `resetDataTableState()` are removed. Rows a
controller resolved are an input of one render, not state on the shared table. `null` (the default)
renders the configured source, and `rows: []` renders an empty inline table without falling back to
the entity source.

```php
// Before (1.x)
$table->rows($users->findBy([], ['lastName' => 'ASC']));

return $this->render('admin/users/index.html.twig', ['table' => $table]);
```

```php
// After (2.0)
return $this->render('admin/users/index.html.twig', [
    'table' => $table,
    'users' => $users->findBy([], ['lastName' => 'ASC']),
]);
```

```twig
{{ render_datatable(table, {}, users) }}
{# or #}
{{ render_datatable(table, rows: users) }}
```

When the table is rendered without Twig, pass the rows to the runtime:

```php
$prepared = $runtime->render($table, rows: $users);
```

### A payload key set twice throws

Options, extensions and bundle keys share one payload. A key set twice, for example an option and
an extension with the same name, now throws a `LogicException` at render instead of silently
overwriting the first value. Rename or remove the duplicate.

### `DataTablesExtension` takes fewer constructor arguments

If you build the rendering by hand, `DataTablesRuntime` takes the Stimulus helper, the
authorization checker, the `DataTableRuntime` and the profiler (`DataTablesExtension` has no
constructor, see above): drop the column resolver, request stack, CSRF token manager,
Ajax registry and controller name arguments. The bundle's service needs no change.
`DataTableProfiler::collectRenderedTable()` now receives the prepared payload and the configured
table.

### A fluent API Platform opt-out wins over the attribute

`apiPlatform(false)` in `configureDataTable()` now overrides `#[AsDataTable(apiPlatform: true)]`. The
table gets no auto-detected columns, and its rows, exports, bulk and row actions are read through
Doctrine. Every opt-in follows the same rule: a fluent value overrides the attribute, and a `null` or
blank value counts as unset. If you combined the two to stay on API Platform, remove
`apiPlatform(false)`; otherwise remove the attribute flag and declare the columns in
`configureColumns()`.

### `ajax()` with an API Platform opt-in sends the API Platform flag

`#[AsDataTable(apiPlatform: true)]` combined with `ajax()` now puts the API Platform flag in the
payload, as `->apiPlatform()` already did, so the browser reads the endpoint through the Hydra
adapter, and SearchBuilder, ColumnControl searches and summaries throw at render. To keep plain
DataTables JSON, add `->apiPlatform(false)`:

```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table->ajax('/books.json')->apiPlatform(false);
}
```

### Contradictory API Platform opt-ins throw

Both 1.6 deprecations now throw a `LogicException`:

- an API Platform opt-in with no collection to read (no collection operation, or API Platform not
  installed) throws at render. The data and export routes keep answering 404. Expose a collection
  operation or drop the opt-in;
- `data()` or the `rows` render argument combined with an API Platform opt-in throws when the table is configured.
  Keep one of them, or add `apiPlatform(false)` over an attribute opt-in.

### Row actions need the URLs from the payload

The browser no longer falls back to the default `/datatables/ajax/...` paths when the payload
carries no `rowActionUrls`: a delete, toggle, detail or edit click fails with a
`datatables:action:error` event and the generic failure message instead. Every payload rendered
since 1.5.1 carries the URLs. Purge HTTP or page caches that hold pages rendered before 1.5.1, and
import the bundle's routes so the row action URLs can be generated.

### Entity tables are server-side by default

An entity table (`#[AsDataTable(entityClass: ...)]`) that never calls `serverSide()`, `ajax()` or
`data()` no longer reads and embeds every row at render time: the browser fetches one page at a
time from the bundle's Ajax data route. This is the change the 1.6 "never called serverSide()"
deprecation announced, and that deprecation is removed. A table that already calls `serverSide()`
or `serverSide(false)` is unchanged.

To keep a small lookup table in the page, with instant search and sort in the browser, ask for the
embedded rows explicitly:

```php
// Before (1.x): rows embedded by default
#[AsDataTable(entityClass: Country::class)]
final class CountryTable extends AbstractDataTable
{
}

// After (2.0): rows embedded only on request
#[AsDataTable(entityClass: Country::class)]
final class CountryTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide(false);
    }
}
```

- A switched table is served by the bundle's Ajax data route (`/datatables/ajax/data` unless you
  import the routes under a prefix). A table token does not authenticate anyone: protect that route
  with your Symfony Security configuration (`access_control` or a firewall). The route applies the
  same table access check, static column permissions, table-purpose token and row scope as for any
  server-side table.
- With `#[AsDataTable(mercure: true)]`, a switched table now subscribes to the Mercure updates
  auto-resolved from its entity, which an embedded table never did. Make sure your hub authorizes
  those topics, or add `serverSide(false)` to keep the old behavior.
- `data_tables.hydration_row_threshold` still logs a warning when a `serverSide(false)` table embeds
  more rows than the threshold.

### Custom columns must extend `AbstractColumn`

`AbstractColumn` is the only column implementation. `ColumnInterface` stays as the read-only view
handed to extension points, and loses `setField()` and `setVisible()`. `DataTable::add()`,
`DataTable::columns()` and `configureColumns()` accept `AbstractColumn` instances only, and passing
anything else fails when the table is configured. Summaries, value formatting, ColumnControl search
and export formatting used to skip a column that was not an `AbstractColumn`: with the type enforced,
every column gets them.

- A class that implements `ColumnInterface` directly must extend a bundled column or `AbstractColumn`
  instead. Set the type in the constructor and drop the members `AbstractColumn` already provides.
- `SearchableColumnInterface` and `NormalizedSearchColumnInterface` are removed. `AbstractColumn`
  carries `getSearchField()`, `getSearchJoins()`, `buildSearchPredicate()` and `isSearchNormalized()`.
- Search strategies, search predicate builders and `SearchListOptionsProviderInterface`
  implementations keep receiving a `ColumnInterface`: their signatures do not change.
- `AbstractDataTable::getColumnByName()` and `DataTable::getColumnByName()` return `?AbstractColumn`.
- `ColumnTranslator::applyTitle()` no longer falls back to a `setTitle()` method.

```php
// Before (1.x)
final class RatingColumn implements ColumnInterface
{
    public function setField(string $field): static { /* ... */ }
    // ... every other getter
}

// After (2.0)
final class RatingColumn extends AbstractColumn
{
    public static function new(string $name, string $title = ''): static
    {
        return static::createWithType($name, $title, ColumnType::NUM);
    }
}
```

### The `#[Column]` attribute is removed

Replace `#[Column(...)]` with `#[DataTableColumn(...)]`. Every named parameter of `#[Column]` is now an
entry of the `options` array, except `type`, `name` and `position`, which stay parameters.

```php
// Before (1.x)
#[Column(type: EmailColumn::class, title: 'Email', orderable: false)]
private string $email;

// After (2.0)
#[DataTableColumn(type: EmailColumn::class, options: ['title' => 'Email', 'orderable' => false])]
private string $email;
```

### Attribute options reach bare methods only

`#[DataTableColumn]` and `#[DataTableFilter]` options are applied through the bare fluent method
(`foo()`) only. A custom column or filter that answered an option through its own `setFoo()` method
must declare `foo()`, otherwise the option throws "is not supported by".

```php
// Declaration, unchanged
#[DataTableColumn(type: RatingColumn::class, options: ['max' => 5])]
private int $rating;

// Before (1.x): RatingColumn answered the option through its own setter
public function setMax(int $max): static { /* ... */ }

// After (2.0): declare the bare method
public function max(int $max): static { /* ... */ }
```

### One data provider contract

`DataProviderInterface` now declares `supports()` and `iterateRows()` next to `fetchData()`. A
custom provider that implements only `fetchData()` fails to load until it adds both. Export always
streams through `iterateRows()`, and the bundle no longer assumes that a provider without
`supports()` handles every feature.

```php
// Before (1.x)
final class WarehouseStockProvider implements DataProviderInterface, FeatureAwareDataProviderInterface, StreamingDataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult { /* ... */ }

    public function supports(DataSourceFeature $feature): bool { /* ... */ }

    public function iterateRows(DataTableRequest $request): iterable { /* ... */ }
}

// After (2.0)
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Enum\DataSourceFeature;
use Pentiminax\UX\DataTables\Model\DataTableResult;

final class WarehouseStockProvider implements DataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult
    {
        // ...
    }

    public function supports(DataSourceFeature $feature): bool
    {
        return match ($feature) {
            DataSourceFeature::ConfiguredFilters => true,
            default                              => false,
        };
    }

    public function iterateRows(DataTableRequest $request): iterable
    {
        return $this->fetchData($request->withoutPagination())->data;
    }
}
```

The `iterateRows()` above loads every row in memory during an export; yield rows in batches instead
when the table is large.

A `supports()` that throws an `\UnhandledMatchError` for a feature it does not list still means
unsupported, but no longer logs a deprecation. Row scope and identifier collection need
`IdentifierDataProviderInterface` and a `true` answer from `supports()` for
`DataSourceFeature::RowScope` and `DataSourceFeature::IdentifierCollection`; implementing the
interface alone no longer declares them. A provider that answers `false` (or `default => false`) for
`RowScope` cannot check the row scope, and is refused for row actions and bulk actions unless the
table calls `unscopedMutations()` (see "Row actions and bulk actions refuse an unscoped data
provider"). For `IdentifierCollection`, a bulk "select all matching" is rejected.

### Row actions and bulk actions refuse an unscoped data provider

Row actions (delete, boolean toggle, edit form, detail row) and bulk actions now fail closed. 1.x let
them run with a deprecation when the table's data provider could not check the row scope, which is
the case for an API Platform table, `ArrayDataProvider` and a custom provider that does not declare
`DataSourceFeature::RowScope`. 2.0 refuses them:

- A table with row actions or bulk actions on such a provider throws a `LogicException` naming
  `unscopedMutations()` when it renders, whatever its data source mode. Only the actions the bundle
  serves through its Ajax endpoints count: delete, edit, a collapsible detail, a boolean switch and
  bulk actions. Link-only actions and custom actions do not. The Ajax endpoints answer `403`.
- A provider whose `supports()` throws an `\UnhandledMatchError` for `RowScope` counts as unscoped,
  and is refused the same way.
- An entity without an identifier field answers `403` instead of skipping the scope check.
- A mutation that runs without a data table request answers `403`.
- A boolean toggle on a table without an entity class answers `404`, like a row that does not exist.
  Give the table an entity class with `#[AsDataTable(entityClass: ...)]`.

If the scope is enforced elsewhere (an API Platform extension, a voter), say so on the table. Otherwise
make the provider scoped.

```php
// Before (1.x): logged a deprecation, ran unscoped
public function configureDataTable(DataTable $table): DataTable
{
    return $table->apiPlatform();
}

// After (2.0): opt out explicitly
public function configureDataTable(DataTable $table): DataTable
{
    return $table->apiPlatform()->unscopedMutations();
}

// Or: scope a custom provider
final class TenantProvider implements DataProviderInterface, IdentifierDataProviderInterface
{
    public function supports(DataSourceFeature $feature): bool
    {
        return \in_array($feature, [DataSourceFeature::RowScope, DataSourceFeature::IdentifierCollection], true);
    }

    // collectIdentifiers() and filterIdentifiersInScope() keep the ids of the tenant
}
```

`unscopedMutations()` adds no check: the scope is yours to enforce. It is not deprecated.

On an unscoped provider a bulk action handler receives the entities of every identifier the browser
sent, as long as a row with that identifier exists. The bundle cannot tell whether the current user
may act on them, so validate each entity in the handler (ownership, tenant, state) and decline the
ones that fail with `$context->skip()`:

```php
use Pentiminax\UX\DataTables\Mutation\BulkActionContext;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;

BulkAction::new('archive')->handler(function (BulkRecords $records, BulkActionContext $context) use ($security): void {
    foreach ($records as $entity) {
        if ($entity->getOwner() !== $security->getUser()) {
            $context->skip();

            continue;
        }

        $entity->archive();
    }
});
```

### Platform requirements

2.0 requires PHP 8.3 and Symfony 7.4 or 8.x. When you use API Platform it must be 4.0 or later, and
when you use Mercure `symfony/mercure` must be 0.8 or later: Composer refuses older versions with a
`conflict` entry. Check your platform before you upgrade.

### Hand-built services need an authorization checker

The bundle no longer falls back to a checker without Symfony Security when you build one of its
services by hand. The services below take `Pentiminax\UX\DataTables\Security\AuthorizationChecker`
as a required constructor argument, so a missing checker is an error at construction instead of a
deprecation: `ColumnResolver`, `ActionRowDataResolver`, `BooleanMutationContextResolver`,
`AjaxBulkController`, `AjaxDataTableRegistry`, `DataTablesExtension`, `DataTableRuntimeFactory` and
`RenderingPreparer`. `ExportService` and `EditFormService` require a `ColumnResolver`, and
`RowProcessingPipeline` requires a `ColumnResolver` and an `ActionRowDataResolver`. Services from the
container already receive them: change nothing there.

The checker comes first in `ColumnResolver`, `ActionRowDataResolver`, `DataTableRuntimeFactory`,
`RenderingPreparer` and `DataTablesExtension` (second there, after the Stimulus helper), so pass
it by name or move the arguments that follow it.

```php
// Before (1.x)
$resolver = new ColumnResolver($attributeColumnReader);

// After (2.0)
$resolver = new ColumnResolver(new AuthorizationChecker($security), $attributeColumnReader);
```

`EntityMutator`, `EditFormService` and `DetailRowService` never read the locator, the row scope guard
or the checker they declared, so those arguments are gone: `new EntityMutator($accessor, $publisher,
$topicResolver, $flusher)`, `new DetailRowService($twig)`, and `EditFormService` ends with the
`ColumnResolver`. Application code should not build these services: the Ajax controllers do.

### Invalid configuration throws

Four mistakes that logged a deprecation in 1.x now throw. Fix the configuration or the table.

| Mistake | Exception |
| --- | --- |
| `data_tables.options.pageLength` is not one of `data_tables.options.lengthMenu` | `InvalidConfigurationException` when the container compiles |
| A `data_tables.extensions.buttons` value is not a `ButtonType` case | `InvalidConfigurationException` when the container compiles |
| `data_tables.extensions.select.style` is not a `SelectStyle` case | `InvalidConfigurationException` when the container compiles |
| `Feature::SEARCH_BUILDER` is in the layout without the SearchBuilder extension | `LogicException` from `DataTable::getOptions()` |

```php
// Before (1.x): logged a deprecation
$table->layout(['top1' => Feature::SEARCH_BUILDER]);

// After (2.0)
$table->searchBuilder();
```

Environment placeholders are still accepted, since they resolve after validation.

### Deprecations removed

Everything below was deprecated in 1.x and is removed. Each line names its replacement.

- Applying an attribute option through a `setFoo()` method: declare `foo()`.
- `setClassName()`, `setCellType()`, `setName()`, `setOrderable()`, `setSearchable()`,
  `setSearchNormalization()`, `setColumnControl()`, `setType()`, `setWidth()`,
  `setResponsivePriority()`, `setTitle()`, `setVisible()`, `setData()`, `setDefaultContent()`,
  `setExportable()`, `setField()`, `setOrderExpression()`, `setSearchField()`,
  `setSearchPredicate()`, `setCustomOption()` and `setPermission()` on columns: use the bare method
  (`className()`, `cellType()`, ...).
- `ImageColumn::setImageWidth()`, `setImageHeight()`, `setAlt()` and `setPlaceholder()`: use
  `imageWidth()`, `imageHeight()`, `alt()` and `placeholder()`.
- `ChoiceColumn::setChoices()`: use `choices()`.
- `UrlColumn::setDisplayValue()` and `setDefaultProtocol()`, `EmailColumn::setDisplayValue()`: use
  `displayValue()` and `defaultProtocol()`.
- `DateColumn::setFormat()`: use `format()`.
- `BooleanColumn::setToggleAjax()` and `setEntityClass()`: use `toggleAjax()` and `entityClass()`.
- `TemplateColumn::setTemplate()`: use `template()`.
- `ColumnResolver::removeDeniedColumnValues()`: use `deniedColumnPaths()` and `removePaths()`.
- `ColumnInterface::setField()` and `setVisible()`: use `AbstractColumn::field()` and `visible()`.
- `SearchableColumnInterface` and `NormalizedSearchColumnInterface`: extend `AbstractColumn`.
- `Pentiminax\UX\DataTables\Attribute\Column`: use `DataTableColumn`.
- `setClassName()`, `setEntityClass()`, `setIdField()` and `setPermission()` on `Action`;
  `setClassName()` and `setPermission()` on `BulkAction`; `setColumnLabel()`, `setColumnClassName()`
  and `setColumnControl()` on `Actions`; `setIdField()` on `BulkActions`; `setLanguage()` and
  `setSearch()` on `DataTableOptions`; `setAttributes()`, `setPermission()` and
  `setHighlightConfig()` on `DataTable`: use the bare method (`className()`, `entityClass()`, ...).
- `DataTable::setExtensions()`: declare extensions with `extensions([...])`.
- `Action::csrfTokenId()`: use `csrfToken()`.
- `AbstractDataTable::setData()`: pass the rows as the `rows` argument of `render_datatable()`.
- `AbstractDataTable::rows()`: pass the rows as the `rows` argument of `render_datatable()`, or to
  `DataTableRuntime::render($table, rows:)` without Twig. See "Inline rows are passed to
  `render_datatable()`".
- `EntityMutator::delete()` and `setProperty()`, `DetailRowService::handleView()`,
  `EditFormService::handleView()` and `handleSubmit()`: the bundle's Ajax controllers call
  `deleteRow()`, `toggleRow()`, `render()`, `renderForm()` and `submitForm()` after the row gate has
  authorized the row. Do not call the services from application code.
- The `$filters` argument of `ArrayDataProvider` and the `$name` argument of
  `FilterTypeMapper::mapType()`.
- `FeatureAwareDataProviderInterface`: implement `supports()` on `DataProviderInterface`.
- `StreamingDataProviderInterface`: implement `iterateRows()` on `DataProviderInterface`.
- `ScopedIdentifierProviderInterface` and `IdentifierCollectingDataProviderInterface`: implement
  `IdentifierDataProviderInterface` and declare `DataSourceFeature::RowScope` and
  `DataSourceFeature::IdentifierCollection` in `supports()`.
- The frontend modules `functions/filterFeature.js`, `functions/filterLayout.js`,
  `functions/bulkActionsFeature.js` and `functions/bulkActionsLayout.js`, and `confirmBulkAction()`:
  use `installFilterBar()` from `functions/filters.js`, `installBulkActionBar()` from
  `bulk/BulkActionBar.js` and `confirmAction()` from `bulk/confirmModal.js`.

  ```js
  // Before (1.x)
  import { confirmBulkAction } from '@pentiminax/ux-datatables/dist/bulk/confirmModal.js'

  // After (2.0)
  import { confirmAction } from '@pentiminax/ux-datatables/dist/bulk/confirmModal.js'
  ```

### `ArrayDataProvider` loses its `$filters` argument

The fourth constructor argument is removed, so `$intentFactory` moves from fifth to fourth position.
The bundle rejects values for the table's configured filters on its own, so drop the argument:

```php
// Before (1.x)
new ArrayDataProvider($rows, $mapper, $this->getResolvedColumns(), $filters);

// After (2.0)
new ArrayDataProvider($rows, $mapper, $this->getResolvedColumns());
```

If you pass an intent factory positionally, pass it by name: `intentFactory: $factory`.

## Upgrading to 1.8.0

Everything in this release is backward compatible. It adds a third argument to `render_datatable()`
and deprecates what it replaces.

### Deprecations

- `AbstractDataTable::rows()` is deprecated, pass the rows as the `rows` argument of
  `render_datatable()` instead. The table holds the rows only for that render, and
  `rows: []` renders an empty table without falling back to the entity source. `null` (the
  default) keeps rendering the configured source. The deprecated `setData()` points to the same
  replacement. Code that renders a table without Twig keeps `rows()` until 2.0, where
  `DataTableRuntime::render($table, rows:)` replaces it.
  A table rendered with rows is reconfigured on its next render, so state you set through
  `getConfiguredDataTable()` before a render is not kept across two renders.

  ```php
  // before
  $table->rows($users->findBy([], ['lastName' => 'ASC']));

  return $this->render('admin/users/index.html.twig', ['table' => $table]);
  ```

  ```php
  // after
  return $this->render('admin/users/index.html.twig', [
      'table' => $table,
      'users' => $users->findBy([], ['lastName' => 'ASC']),
  ]);
  ```

  ```twig
  {# before #}
  {{ render_datatable(table) }}

  {# after #}
  {{ render_datatable(table, rows: users) }}
  {{ render_datatable(table, {class: 'table'}, users) }}
  ```

  A template variable that is missing evaluates to `null` when Twig `strict_variables` is off, so
  the table renders its configured source (still scoped by `customizeQueryBuilder()`).

## Upgrading to 1.7.0

1.7 deprecates what 2.0 removes. Everything in it is backward compatible, so that 2.0 is a cleanup. An application that runs 1.8.0 (the latest 1.x) with no deprecation notice upgrades to 2.0
with `composer update` and one install command. To rename everything with an AI coding agent, give it
[UPGRADE-AI.md](UPGRADE-AI.md).

### 2.0 platform requirements

2.0 requires PHP 8.3, Symfony 7.4 or 8.x, API Platform 4 or later (when you use it) and
`symfony/mercure` 0.8 or later (when you use it). No deprecation notice can announce a platform
floor, so check your platform before upgrading to 2.0.

### Deprecations

- `AbstractDataTable::handleRequest()`, `isRequestHandled()` and `getResponse()` are deprecated, use
  the new `DataTableResponder::respond()`. It returns the DataTables JSON response, or `null` when the
  request carries no DataTables payload, and it still records the query in the Web Profiler.
  Inject `Pentiminax\UX\DataTables\Runtime\DataTableResponder` in your controller:

  ```php
  // before
  $table->handleRequest($request);

  if ($table->isRequestHandled()) {
      return $table->getResponse();
  }

  // after
  if ($response = $responder->respond($table, $request)) {
      return $response;
  }
  ```

- `AbstractDataTable::getRequest()` is deprecated: inside the table, call `getCurrentRequest()` (for
  `mapRow()`, `projectPage()` and other hooks); `customizeQueryBuilder()` already receives the
  `DataTableRequest` as its `$request` argument. Stop calling it from controllers and services.
- `AbstractDataTable::fetchData()` and `getDataProvider()` are deprecated and have no replacement:
  2.0 moves them to the bundle runtime. Stop calling them from controllers and services; call the
  data provider you built yourself.
- A subclass that overrides `handleRequest()`, `isRequestHandled()`, `getResponse()`, `getRequest()`
  or `fetchData()` keeps being called by the bundle in 1.x and now logs a deprecation, once per
  class and method, even when its override does not call `parent::`. 2.0 no longer calls the
  overrides: use `DataTableResponder::respond()` instead of the three response methods, and move
  the logic of the other two to `customizeQueryBuilder()` (or `getCurrentRequest()` for request
  reads).
- The `--prune` option of `ux-datatables:install` is deprecated and has no replacement: 2.0 removes
  it because Symfony Flex no longer declares import map entries to prune. The option keeps its
  behavior and prints a note.

## Upgrading to 1.6.0

Everything in this release is backward compatible, except the behavior changes below. An application that runs 1.6.0 with no
deprecation notice and no console warning upgrades to 2.0 with `composer update` and one install
command. To rename everything with an AI coding agent, give it [UPGRADE-AI.md](UPGRADE-AI.md).

### Subclasses of columns and tables

The bare methods are new public methods on `AbstractColumn`, the concrete columns, `AbstractDataTable`
(`rows()`), `Action`, `BulkAction` and the table options. A subclass that already declares a method
with one of these names (`rows()`, `data()`, `name()`, `type()`, `title()`, `format()`, `template()`,
`choices()`, ...) must rename it or match the new signature. A subclass that overrides a deprecated
`setX()` must override `x()` instead: the bundle now calls `x()`, so the override is no longer
reached.

### Displayed to-one relations are loaded with the page

A server-side table backed by the Doctrine data provider now fetch-joins the to-one associations
its displayed columns read through a dot path (`customer.name`), instead of lazy-loading one
related entity per row. Rows, counts and the JSON payload are identical, and the number of queries
per draw no longer grows with the page length. An application that relied on the related entity
being a lazy proxy (an `instanceof Proxy` check, or a `postLoad` listener on the related entity
firing later) sees it loaded earlier. A join you add in `customizeQueryBuilder()` is reused when it
is a plain `LEFT JOIN` or when your query already selects its alias; any other join with a
condition, or `INNER JOIN`, is left alone. A query made DISTINCT in `customizeQueryBuilder()` gets
no fetch join (PostgreSQL cannot compare json columns under `SELECT DISTINCT`), so its related
entities still load lazily.

### Ajax data and export refuse an API Platform table without a collection

A table opted into API Platform (`#[AsDataTable(apiPlatform: true)]` or `apiPlatform()`) whose
entity exposes no collection operation already rendered with no rows, because a Doctrine fallback
would skip the authorization the operation applies. The Ajax data and export endpoints used to
ignore that and serve Doctrine rows anyway. They now answer 404, and the page no longer renders an
export URL for such a table. This also applies to a table that provides its own
`createDataProvider()`. Bulk and row actions on such a table answer 404 too, and calling
`getDataProvider()`, `fetchData()` or `getResponse()` on it throws `NotFoundHttpException`.

Expose a collection operation on the entity, or drop the opt-in to read the table through Doctrine
on your own authorization:

```php
// Before
#[AsDataTable(entityClass: Product::class, apiPlatform: true)]

// After
#[AsDataTable(entityClass: Product::class)]
```

### Row scope, identifier collection and enum cases

`DataSourceFeature` gains the cases `RowScope` and `IdentifierCollection`. A `supports()` written as
an exhaustive `match` without a default throws an `\UnhandledMatchError` for them; the bundle catches
it and triggers a deprecation naming your provider. For a query feature (SearchBuilder, ColumnControl,
configured filters, summaries) an unknown case counts as unsupported and a request using it is
refused. For row scope and identifier collection, a provider implementing
`IdentifierDataProviderInterface` keeps being used, so the scope check still runs and a forged
out-of-scope id is filtered out. Add the two cases or a `default` arm to `supports()`, and to any of
your own code that iterates `DataSourceFeature::cases()` or matches on it exhaustively:

```php
public function supports(DataSourceFeature $feature): bool
{
    return match ($feature) {
        DataSourceFeature::RowScope, DataSourceFeature::IdentifierCollection => true,
        default => false,
    };
}
```

### The fluent API Platform flag is withdrawn from a table with embedded rows

A table that combines `data()` with the fluent `apiPlatform()` used to send the API Platform flag
with its embedded rows, and the browser adapter then failed on the missing Ajax option. The flag is
now withdrawn from the payload and the table renders its rows. Drop the call:

```php
// Before
$table->data($rows)->apiPlatform();

// After
$table->data($rows);
```

### A blank edit modal template or adapter counts as unset

`editModalTemplate('')` and `editModalAdapter('')` used to override the `#[AsDataTable]` value on the
page while the Ajax edit route ignored them. A blank value now counts as unset in both places, so
the attribute's value (or the default) applies. Replace a blank call with the template or adapter
you want, or remove it:

```php
// Before
$table->editModalTemplate('');

// After
$table->editModalTemplate('modal/edit.html.twig');
```

### Deprecations

- Acting on rows (delete, toggle, edit form, detail, bulk explicit selection) of a table whose data
  provider cannot check the row scope skips the scope check and now logs a deprecation. 2.0 refuses
  the request. Implement `IdentifierDataProviderInterface` and declare `DataSourceFeature::RowScope`
  in `supports()`, or call `unscopedMutations()` on the table when the scope is enforced elsewhere,
  typically an API Platform table. The built-in Doctrine provider is already scoped.
- `ScopedIdentifierProviderInterface` is deprecated, use `IdentifierDataProviderInterface` and
  declare `DataSourceFeature::RowScope` in `supports()`. A provider still answering through it
  logs a deprecation.
- `IdentifierCollectingDataProviderInterface` is deprecated, use `IdentifierDataProviderInterface` and
  declare `DataSourceFeature::IdentifierCollection` in `supports()`. A provider still answering
  through it logs a deprecation.
- Exporting through a data provider that does not implement `StreamingDataProviderInterface` is
  deprecated, and 2.0 requires `iterateRows()`. Implement `StreamingDataProviderInterface`.
- A data provider that does not implement `FeatureAwareDataProviderInterface` is deprecated: the
  bundle assumes it supports every query feature, and 2.0 requires `supports()`. Implement
  `FeatureAwareDataProviderInterface`.
- A data provider whose `supports()` throws an `\UnhandledMatchError` for a `DataSourceFeature` case
  logs a deprecation: the bundle keeps the row scope check and identifier collection and refuses
  the query feature. 2.0 lets the error propagate. Add the case or a `default` arm to the `match`.
- `AbstractDataTable::getDataTable()` and `prepareForRendering()` mutate the table while preparing it
  for rendering. Use `getConfiguredDataTable()` to read the table and `render_datatable()` to render
  it.
- `MercureUpdatePublisher::publishForDataTable()` with a prepared `DataTable` is deprecated. Pass the
  `AbstractDataTable` instead: `publishForDataTable($table)` rather than
  `publishForDataTable($table->getDataTable())`.
- `DataTable::apiPlatformServerSide()` is deprecated: the bundle sets the flag itself when a table
  needs it, so remove the call.
- Every user-facing `setX()` method of columns, actions, bulk actions, table options and the table
  model gains a bare `x()` method with the same arguments, and `setX()` is deprecated. Rename the
  calls, for example `setTitle('Name')` becomes `title('Name')`. Getters, `@internal` methods and
  `#[DataTableColumn(...)]` attribute options are unchanged.
- An attribute option that a custom column only accepts through its own `setFoo()` method logs a
  deprecation, and 2.0 calls only `foo()`. Add a `foo()` method to the column.
- The deprecated frontend modules and `confirmBulkAction()` from 1.2 now log a console warning
  when imported or called.
- `DataTable::setExtensions()` replaces the whole extension collection and has no replacement.
  Declare extensions with `extensions([...])`.
- `Action::csrfTokenId()` is deprecated, use `csrfToken()`.
- Custom columns must extend a bundled column or `AbstractColumn`. Implementing `ColumnInterface`
  directly is deprecated: adding such a column to a table, through `add()`, `columns()` or
  `configureColumns()`, raises a deprecation, and the column already misses summaries, value
  formatting and search features. `ColumnInterface::setField()` and `setVisible()` are deprecated,
  use `field()` and `visible()` on the column. `SearchableColumnInterface` and
  `NormalizedSearchColumnInterface` are deprecated, `AbstractColumn` carries both. The interface
  stays as the read-only type of search strategies and SearchList options providers.
- A bundle service built by hand without an authorization checker falls back to a checker without Symfony Security,
  which 2.0 removes. Pass an `AuthorizationChecker`. Services from the container are not affected.
- Stylesheet autodetection of the style framework logs a console warning once per page, in
  registered and built-in package loading alike, and 2.0 removes it: the default becomes `dt`.
  Set `data_tables.style_framework` to `dt` or `bs5`, call `styleFramework()` on the table, or
  re-run `bin/console ux-datatables:install`, which now writes the key.
- An API Platform opt-in (`#[AsDataTable(apiPlatform: true)]` or `apiPlatform()`) with no collection
  to read, because the entity exposes no collection operation or API Platform is not installed, logs
  a deprecation, and 2.0 throws a `LogicException`. Expose a collection operation or drop the opt-in.
- An entity table that never calls `serverSide()` reads and embeds every row at render time, and now
  logs a deprecation once per table class: 2.0 makes server-side the default. Call `serverSide()`
  to adopt it now, or `serverSide(false)` to keep embedding the rows. A server-side table is served
  through the bundle's Ajax data route, which your Symfony Security configuration must protect, and
  its Mercure updates are then auto-resolved, which an embedded table does not get. The bundle also
  logs a warning past `data_tables.hydration_row_threshold` embedded rows (default `5000`, `0`
  disables it).
- `data()` combined with `apiPlatform()`, or with `#[AsDataTable(apiPlatform: true)]` unless
  `apiPlatform(false)` is also set, logs a deprecation, and 2.0 throws a `LogicException`: the two read
  rows from different places. Keep one.
- `#[AsDataTable(apiPlatform: true)]` combined with `apiPlatform(false)` still opts in, and logs a
  deprecation: 2.0 lets the fluent value win. Remove one of the two. Combined with `data()` or `ajax()`,
  the payload already follows the fluent `false`, but the server still follows the attribute: it
  auto-detects the columns from the API Platform resource when `configureColumns()` declares none, and
  reads the rows of exports, bulk actions and row actions through the API Platform provider. 2.0 lets
  `apiPlatform(false)` win there too: auto-detected columns disappear and those reads go through
  Doctrine. The table cannot tell whether an export or an action will ever read rows, so the
  combination always logs one deprecation, which names the auto-detected columns when the table relies
  on them. Declare the columns in `configureColumns()` and remove the attribute flag.
- `#[AsDataTable(apiPlatform: true)]` combined with `ajax()` and no `apiPlatform()` call renders plain
  DataTables JSON today and logs a deprecation: 2.0 sends the API Platform flag for it, so the browser
  reads the response through the Hydra adapter. Add `->apiPlatform(false)` to keep plain DataTables
  JSON, or `->apiPlatform()` to opt in now. `ajax()` combined with the fluent `apiPlatform()` is
  unchanged.

### The option bag returns only DataTables.net options in 2.0

`DataTable::getOption()` and `getOptions()` also return bundle keys today (`apiPlatform`,
`apiPlatformServerSide`, `mercure`, `bulkActions`, ...). 2.0 returns only DataTables.net options, and
the bundle keys live on the prepared payload. No notice can be raised for a read, so check code that
reads a bundle key from the option bag and read the table configuration instead.

### Doctrine ORM 3.3 and OpenSpout 4.13 are the tested floors

CI now runs the suite on the lowest dependencies. It showed that Doctrine-backed tables need
Doctrine ORM 3.3 or later: earlier 3.x releases reject the array parameter types the bundle binds.
Server-side XLSX export needs OpenSpout 4.13 or later, which provides `setColumnWidthForRange()`.
ORM and OpenSpout stay optional packages, so Composer does not enforce either floor. Upgrade them
before upgrading the bundle.

## Upgrading to 1.5.1

Everything in this release is backward compatible. The deprecations below tell you what 2.0
removes. An application that runs 1.5.1 with no deprecation notice and no console warning upgrades to
2.0 without surprises.

### Deprecations

- `data_tables.package_loading: builtin` is deprecated, like leaving the option unset. Set
  `registered` (run `ux-datatables:install`). 2.0 removes the option: `builtin` fails.
- A boolean toggle on a table without an entity class skips the row scope check. It still runs in
  1.5.1 with a deprecation. 2.0 refuses the request. Give the table an entity class with
  `#[AsDataTable(entityClass: ...)]`. Toggles that are already scoped raise nothing.
- Bootstrap 3 and 4 picked up by stylesheet autodetection log a console warning once per page, and
  `StyleFramework` `'bs'` and `'bs4'` are tagged `@deprecated` in TypeScript. Load the Bootstrap 5
  or the DataTables stylesheet instead.

### Row actions follow the route prefix

Delete, boolean toggle, detail, and the edit form view and submit now call the URLs the router
generates, which the table serializes under a new `rowActionUrls` payload key. An application that
imports the bundle routes under a prefix no longer needs to proxy `/datatables/ajax/*`. Without the
key, for example in a page cached before the upgrade, the frontend calls the previous default
paths. A custom JavaScript client keeps working. An explicit `data-url` on a switch still wins.

### Edit form routes need `symfony/form`

Without `symfony/form`, the two edit form routes are no longer registered: a request to them
answers `404` instead of a server error.

### Missing optional JavaScript packages degrade one feature

- A table with an icon initializes without Lucide registered, without its icons, and logs a console
  warning. It used to fail to initialize.
- An `excel` or `pdf` button logs a console warning when JSZip or pdfmake is not registered, and a
  JSZip or pdfmake that fails to load disables only its button.
- `packageLoaders.register()` throws for a name outside the closed list of packages, so a typo
  fails at startup.

### Registered package loading: what to import and what changes

- In registered mode, import `packageLoaders`, `buttonActions`, `modalAdapters` and the adapter
  types from `@pentiminax/ux-datatables/dist/core.js`. The main entry still re-exports the 1.x
  controller, so importing it brings the built-in loaders back into your bundle. The documentation
  now shows the core entry.
- Registered loading reduces the packages you install and your bundler resolves. It does not reduce
  what the browser downloads for the packages a table uses.
- `ux-datatables:install --prune` is not durable: Symfony Flex requires the entries the bundle
  declares again on the next `composer install` or `composer update`. Run the command with
  `--prune` again after a Composer run. 2.0 shrinks the declared import map.

### Installer and published package

- `ux-datatables:install` registers `dist/core.js` through its logical path
  (`@pentiminax/ux-datatables/core.js`), so it works with a custom Composer vendor directory.
- The installer pins a package and its stylesheet to the same version, which satisfies the range
  the bundle declares. A mapped package outside that range is pinned again.
- The import map and peer dependency ranges, and each stylesheet and its package, now declare the
  same range, and `package.json` carries the release version.
- The frontend build ships type declarations (`dist/**/*.d.ts`). TypeScript code that imports the
  package is now type-checked against them.

## Upgrading to 1.5.0

### Related-entity switch toggles are tied to their table row

A `BooleanColumn` switch that sets another `setEntityClass()` than its table skipped the row scope:
any id of that entity could be toggled. The toggle request now carries `rowId`, the id of the table
row the switch sits in. The server answers `404` unless that row is in scope and the
`setToggleAjax()` id field of that row resolves to the posted `id`.

- Custom clients that toggle a switch on a related entity must send `rowId`. Rebuild or update your
  assets if you use the bundled JavaScript, which already sends it.
- A table whose entity has a composite identifier can no longer toggle a related-entity switch.
- Same-entity toggles are unchanged.
- If you build `AjaxEditRequestDto` yourself, it has a new optional `$rowId`.

### Set `package_loading` explicitly

Leaving `data_tables.package_loading` unset is deprecated. Choose one of the two modes:

```yaml
# config/packages/data_tables.yaml
data_tables:
    package_loading: registered # "builtin" keeps today's behavior but is deprecated since 1.5.1
```

- `builtin` keeps the `datatable` controller, which imports every DataTables package. Setting it
  explicitly is deprecated since 1.5.1.
- `registered` renders the new `datatable_core` controller, which imports only
  `@hotwired/stimulus`. Your application registers the packages it uses.

To switch to `registered`, run the command below and clear the cache. Packages and styles are
described in [Loading only the extensions you use](https://pentiminax.github.io/ux-datatables/guide/package-loading/).

```bash
php bin/console ux-datatables:install --style=tailwind buttons responsive select
```

In `registered` mode:

- a package you did not register fails with `The "<name>" package is not registered.`
- an explicit `package_loading` that contradicts `assets/controllers.json` fails the container
  build in debug
- the Stimulus controller identifier changes from `pentiminax--ux-datatables--datatable` to
  `pentiminax--ux-datatables--datatable-core`: update code that looks the controller up by
  identifier, such as `application.getControllerForElementAndIdentifier()`
- the look is `dt` plus the Tailwind theme; `--style=dt` and `--style=bs5` turn the theme off

In both modes JSZip now loads only for `excel` buttons and pdfmake only for `pdf` buttons.

### Replace the Bootstrap 3 and 4 styles

`StyleFramework::Bootstrap` (Bootstrap 3) and `StyleFramework::Bootstrap4` are deprecated. Use
`StyleFramework::Bootstrap5` or `StyleFramework::DataTables`.

### Stop calling the old row action methods

Delete, boolean toggle, detail, edit form view, and edit form submit now authorize through one
internal gate. Status codes and JSON bodies are unchanged. These methods are deprecated:

| Deprecated | Replacement |
| --- | --- |
| `EntityMutator::delete()` | `EntityMutator::deleteRow()` |
| `EntityMutator::setProperty()` | `EntityMutator::toggleRow()` |
| `DetailRowService::handleView()` | `DetailRowService::render()` |
| `EditFormService::handleView()` | `EditFormService::renderForm()` |
| `EditFormService::handleSubmit()` | `EditFormService::submitForm()` |

The replacements are `@internal` and need an `AuthorizedRow` that only the gate builds. Do not call
either set from application code: go through the bundle's Ajax routes, which carry the checks.

If you build the bundle's controllers by hand, `AjaxDeleteController`, `AjaxEditController`,
`AjaxDetailController`, `AjaxEditFormController`, and `AjaxEditFormSubmitController` now take a
`RowActionGate` instead of their token validator, registry, context resolver, and row scope guard.
`AjaxDetailController` and `AjaxEditFormController` also take the `Request`.

### Prepare for 2.0

2.0 changes the frontend setup. Do the following on 1.5 and the upgrade needs no further change:

1. Run `ux-datatables:install` and fix the deprecations your application reports.
2. Replace `StyleFramework::Bootstrap` and `StyleFramework::Bootstrap4`.
3. Switch to `package_loading: registered`, upgrade to 2.0, run the command again, and remove
   `package_loading` from `data_tables.yaml`.

What 2.0 changes:

- `datatable` becomes the core controller, with the base style, the `dt` CSS, and the Tailwind theme
  autoimported. `datatable_core` stays as a deprecated alias until 3.0.
- `package_loading` is removed: `builtin` fails with a clear message, `registered` is accepted,
  ignored, and deprecated. The built-in loaders are deleted.
- Bootstrap 3 and 4 are removed. The supported styles are `dt` (with or without the Tailwind
  theme) and `bs5`. To drop the Tailwind theme, set its `autoimport` flag to `false`.
- The peer dependencies shrink to `@hotwired/stimulus`; the DataTables packages become optional.
- `--prune` is removed.

## Upgrading to 1.3.0

### Single-row actions respect the table scope

With the built-in Doctrine provider, delete, boolean toggle, edit form, and detail row now answer
`404` for an id that `customizeQueryBuilder()` excludes, like bulk actions already do.

- A toggle on a column that sets another `setEntityClass()` is still unscoped.
- Providers without `ScopedIdentifierProviderInterface` keep their current behavior.
- If you build `AjaxDeleteController`, `AjaxEditController`, `EditFormService`, or
  `DetailRowService` by hand, pass the new required `RowScopeGuard` before the optional arguments.

### Unsupported data source features are refused

The bundle now checks whether the data source can apply SearchBuilder criteria, ColumnControl
searches, filter values, and column summaries.

- An API Platform table that registers the SearchBuilder extension or offers a ColumnControl search
  throws a `LogicException` at render time. The browser used to drop those criteria and show every
  row. Remove the extension, or use a Doctrine or custom provider.
- `ArrayDataProvider` answers HTTP 400 for a configured filter value, instead of a
  `LogicException` (HTTP 500).
- A table that uses `summarize()` cannot be read through API Platform (`LogicException`). A custom
  `DataProviderInterface` supplies the figures through the optional `summaries` argument of
  `DataTableResult`.
- A custom provider can refuse features with the new `FeatureAwareDataProviderInterface`. Without
  it, nothing changes.

### SearchBuilder is strict and applies to every row

- A malformed `searchBuilder` parameter, or a condition the server does not know (such as
  `without`), answers HTTP 400 instead of returning the unfiltered table.
- A criterion on an unknown or `setSearchable(false)` column answers HTTP 400.
- The builder no longer offers the `without` condition and leaves out non-searchable columns.
- SearchBuilder now loads `datatables.net-datetime`. Run `composer update` and rebuild your assets.
  With AssetMapper, also run `php bin/console importmap:require datatables.net-datetime`.

### `#[DataTableFilter]` on `int` and `float` builds a `NumberRangeFilter`

It used to fail while the container compiled. To keep another filter, name its class on the
attribute.

### Edit forms skip columns without a writable property

A column backed by a getter-only property is no longer a form field. It used to fail the submit
with a 500.

### Order direction accepts only `asc` and `desc`

Any other value in `order[][dir]` now sorts ascending.

### `poll()` needs an Ajax source

A table that calls `DataTable::poll()` needs a server-side, `ajax()`, or API Platform source;
otherwise rendering throws a `LogicException`. This includes a source set only from JavaScript
before the controller connects. Intervals outside 2 seconds to 24 hours throw an
`InvalidArgumentException`.

### Deprecations

- The fourth `$filters` argument of `ArrayDataProvider`: drop it.
- `FilterTypeMapper::mapType()`: its second argument is ignored.
- `Feature::SEARCH_BUILDER` in a layout without the SearchBuilder extension: call
  `searchBuilder()` or `addExtension(new SearchBuilderExtension())`. It throws in 2.0.
- YAML values the 1.3 config tree would reject now trigger a deprecation when the container
  compiles, and throw an `InvalidConfigurationException` in 2.0:
  - `data_tables.options.pageLength` that is not in `data_tables.options.lengthMenu`: add it to the
    menu or pick one of its entries.
  - a `data_tables.extensions.buttons` entry that is not a `ButtonType` value, or a
    `data_tables.extensions.select.style` that is not a `SelectStyle` value. Such a value never
    worked: it fails with a `ValueError` when a table is built.

## Upgrading to 1.2.0

### Row identifiers are sent as strings

Identifiers above `Number.MAX_SAFE_INTEGER` (such as snowflake ids) and zero-padded keys such as
`00123` were rounded or reshaped as JSON numbers, so a delete or a toggle could target another
entity. Every identifier exchanged with the browser is now a string:

- the `id` of the built-in delete and boolean toggle requests: `{"id": "42"}`
- the ids under `__ux_datatables_actions` and `__ux_datatables_boolean_switches` in row data
- the `id` of the Mercure `delete` and `edit` updates

Doctrine finds integer primary keys from their string form, so the bundle's endpoints need no
change. Fix custom code that compares these ids strictly to numbers, such as a Mercure listener
using `update.id === 42` or a decorated controller that requires an `int` id.

### The table language follows the request locale

`data_tables.options.language` no longer defaults to `en-GB`. A table without a language loads the
bundled catalog matching the request locale (`fr`, `fr_FR`, and `fr-CA` load French), and keeps
the DataTables English strings when no catalog matches. To pin a language as before, set
`data_tables.options.language` or call `language()` on the table.

### `buttons()` and bulk actions keep the default content of their layout slot

`buttons([...])` used to drop the page length selector, and `buttons([...], 'topEnd')` and the bulk
actions bar used to drop the search box. They now sit next to the default content. To restore the
old result, hide the default before calling `buttons()`, or disable the feature:

```php
$table->layout(['topStart' => null]);
$table->lengthChange(false); // or ->searching(false)
```

### Initial `order()` indexes ignore the selection column

With `select(withCheckbox: true)`, `order([[1, 'asc']])` sorted the column before the one you meant.
Numeric indexes now refer to the columns you declared, like `['name' => ...]` entries always did. If
you compensated by adding 1, remove it.

### Money columns are edited in currency units

The edit form binds a `MoneyColumn` to `MoneyType`: a value stored as cents `2500` opens as
`25.00`, and saving `30.5` stores `3050`. It used to open as `2500`, and saving `25` stored 25
cents. A column with `storedAsCents(false)` is bound as entered. Update tests and custom edit
templates that submit raw cents.

The display scale is now `10^decimals`: `12345` with `decimals(3)` displays as `12.345`, not
`123.45`.

### `symfony/serializer` is required

The edit modal, inline edit, delete, detail rows, and bulk actions map their payload with
`#[MapRequestPayload]`. The bundle now requires `symfony/serializer`, so `composer update` installs
it and FrameworkBundle enables it. An application with its own serializer needs no change.

### Bulk actions skip identifiers outside the table scope

An explicit bulk selection used to load its entities with `findBy()`, ignoring the table scope. The
bundle now keeps only the identifiers that `customizeQueryBuilder()` (without search or filters)
contains. The others are counted in `skipped`, so a handler and `BulkActionContext` may see a
smaller batch. A malformed identifier, such as an invalid UUID, is also counted as skipped.

- The browser now sends the displayed DataTables parameters with an explicit selection, so a
  `customizeQueryBuilder()` that scopes on forwarded query parameters sees the same values as for a
  data request.
- A custom provider opts in by implementing `ScopedIdentifierProviderInterface`. Without it, and
  for API Platform tables, the handler must scope itself.

### Multi-column ordering is applied

Several `order[]` entries used to remove every `ORDER BY`. Every criterion is now applied in order.
`DataTableQueryIntent` gains an `orders` list, and `orderColumn` and `orderDir` mirror the first
criterion instead of being `null`. A custom `QueryFilterInterface` reading them keeps working but
now sees a value for a multi-column sort.

### Server-side pages stay complete when a to-many association is joined

A query joining a collection-valued association (a searchable `tags.label` column, a filter, or a
`leftJoin()` in `customizeQueryBuilder()`) paginated joined SQL rows, so pages came back short or
overlapping. Such a table now pages distinct root identifiers, which costs one extra query per
draw. On a very large result set, prefer a correlated subquery over a collection join. Tables
without a collection join, `GROUP BY` queries, and composite identifiers are unchanged.

### Ordered server-side pages break ties on the identifier

The row identifier is appended to the `ORDER BY`, so rows that share the sorted value keep one order
from page to page. Only rows that tie on the sort key can appear in a different order.

### Mercure updates of private tables are published as private

When a table's Mercure config has `withCredentials` (set explicitly or mapped from API Platform's
`mercure: ['private' => true]`), updates are published with `private: true`. Subscribers whose JWT
does not authorize the topic stop receiving them: give them a subscriber JWT with access to it.

A custom publisher keeps receiving `publish()` and logs a warning for private tables. Implement
`PrivateUpdatePublisherInterface::publishPrivate()` to publish privately.

### `TernaryFilter` compares boolean fields by value

On a Doctrine `boolean` field, `TernaryFilter` checked `IS NOT NULL` and `IS NULL` unless
`values(true, false)` was called, so "Yes" matched every row. It now compares with `true` and
`false`. If you relied on the `NULL` semantics of a nullable boolean, add `nullable()`:

```php
TernaryFilter::new('consent')->nullable();
```

`values()` still wins over both. You can remove a `values(true, false)` workaround.

### Server-side exports write what the table displays

`Button::csv(serverSide: true)` and `Button::excel(serverSide: true)` now match the table:

| Column | Before | Now |
| --- | --- | --- |
| `MoneyColumn` stored as cents | `2500` | `25`, as a number |
| `MoneyColumn` heading | `Price` | `Price (EUR)` |
| `ChoiceColumn` | `draft` | `Draft`, translated |
| `BooleanColumn` | `1` / `0` | `Yes` / `No`, translated |
| Column title `order.total` | `order.total` | `Total`, translated with the request locale |

A column with `formatValueUsing()` is written as its formatter returned it. For a file read by a
machine, keep the previous output with `rawValues()`:

```php
Button::csv(serverSide: true)->rawValues();
```

### `render_datatable()` honors an `id` attribute

`render_datatable(table, {id: 'orders-open'})` replaces the default id instead of emitting a second,
ignored `id`. Attributes passed to one render no longer persist on the shared table instance.

### Action confirmations use the modal adapter

`confirm()` on a delete, detail, edit, or Ajax action now asks through the table's modal adapter
instead of the native dialog, like bulk actions. `window.confirm()` remains the fallback when no
adapter resolves, and link actions keep it. Tests that stub `window.confirm` for a delete button
must answer through the adapter.

### Deprecations

- `ColumnResolver::removeDeniedColumnValues()`: use `deniedColumnPaths()` and `removePaths()`.
- The frontend modules `dist/functions/filterFeature.js`, `filterLayout.js`, `bulkActionsFeature.js`,
  and `bulkActionsLayout.js`: the controller wires both bars itself, so stop deep-importing them.
- `confirmBulkAction()` from `dist/bulk/confirmModal.js`: use `confirmAction()`.

## Upgrading to 1.1.1

### `TextColumn` escapes HTML

DataTables inserts Ajax cell values as HTML, so a `TextColumn` without `html()` rendered
user-controlled markup. Display is now escaped for the `string` and `string-utf8` types. Call
`html()` (or `html()->utf8()`) only when the cell holds trusted, already sanitized markup.

### Update the DataTables packages together

The frontend ranges now use DataTables 3.1.2, ColReorder 3.1.2, FixedHeader 5.1.1, KeyTable 3.1.1,
RowGroup 2.1.1, and Scroller 3.1.1. These extensions require DataTables 3.1 or newer, so update the
core, the styling packages, and the extensions together. Update your importmap pins as well.

RowGroup 2.1.1 escapes group labels in its default `startRender`: a group value such as
`<b>Engineering</b>` displays literally. To render trusted HTML in a group heading, provide a custom
`startRender` through `datatables:pre-connect`. A custom callback must escape untrusted values
itself.

## Upgrading to 1.1.0

### Replace `#[Column]` with `#[DataTableColumn]`

`#[Column]` is deprecated. Each named parameter, except `type`, `name`, and `position`, becomes an
entry of the `options` array:

```php
use Pentiminax\UX\DataTables\Attribute\DataTableColumn;
use Pentiminax\UX\DataTables\Column\EmailColumn;

// before
#[Column(type: EmailColumn::class, title: 'Email', orderable: false, width: '120px')]
private string $email;

// after
#[DataTableColumn(EmailColumn::class, ['title' => 'Email', 'orderable' => false, 'width' => '120px'])]
private string $email;
```

### Handle the new `Feature::BULK_ACTIONS` case

A `match` over `Pentiminax\UX\DataTables\Enum\Feature` without a `default` arm must handle it.

### Check your setup once a table declares bulk actions

These points only apply to a table that declares a bulk action:

- **A voter may receive a `BulkAction`.** `ActionPermissionContext::$action` is typed
  `ExecutableActionInterface`, which `Action` and `BulkAction` implement. Narrow explicitly in a
  voter that assumed `Action`:

  ```php
  // before
  return $subject instanceof ActionPermissionContext;

  // after
  return $subject instanceof ActionPermissionContext && $subject->action instanceof Action;
  ```

- **Rows always carry a `DT_RowId`.** It used to be written only for `highlightUpdates()`. A single
  Doctrine identifier is detected automatically; otherwise call `BulkActions::setIdField()`.
- **The `data_tables.extensions.select` default does not apply.** The table gets a multi-row
  selection with checkboxes. A Select extension the table configures itself is kept, and a
  single-row one throws.
- **Protect `POST /datatables/ajax/bulk`** with your `security.firewalls` configuration, like every
  other bundle Ajax route.
- **Select all needs `IdentifierCollectingDataProviderInterface`** in a custom data provider
  (`DoctrineDataProvider` has it). Its `collectIdentifiers()` receives the field written as
  `DT_RowId`, so answer with values from that field, not the primary key. Without the interface,
  select all answers `400` and an explicit selection keeps working.

### Query building follows `entityClass` when `dataClass` differs

If you pass both to `#[AsDataTable(dataClass: ..., entityClass: ...)]`, a column or filter declared
on the DTO must name a field the entity carries, because ordering and searching build DQL against
`entityClass`. The container checks this while it compiles and names the offender. Dotted paths are
left alone. To make the names differ, redirect the query alone with the `searchField` option or an
`orderExpression`; the `field` option also redirects the displayed value.
