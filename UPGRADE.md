# Upgrade Guide

This guide lists what you may have to change in your application when you upgrade. It covers
behavior changes, breaking changes, and deprecations. New features are not listed here: see the
[release notes](https://github.com/pentiminax/ux-datatables/releases).

Sections are ordered from newest to oldest. When you skip versions, read every section between
your current version (excluded) and the target version (included).

The guide starts at **1.1.0**, the first upgrade after the stable 1.0 release. To upgrade from a
0.x version, read the [guide as published in 1.4.0](https://github.com/pentiminax/ux-datatables/blob/v1.4.0/UPGRADE.md).

Deprecated APIs keep working for the whole 1.x line and are removed in 2.0.

## Upgrading to 1.5.0

### Set `package_loading` explicitly

Leaving `data_tables.package_loading` unset is deprecated. Choose one of the two modes:

```yaml
# config/packages/data_tables.yaml
data_tables:
    package_loading: registered # or "builtin" to keep today's behavior
```

- `builtin` keeps the `datatable` controller, which imports every DataTables package.
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
- `--prune` also removes CSS entries.

## Upgrading to 1.4.1

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
