# Upgrade Guide

Each section covers one version bump. When you skip versions, apply every section between your
current version and the target, oldest first.

## v1.0 → v1.1

### `#[AsDataTable]` separates `dataClass` from `entityClass` (additive)

The attribute now carries two distinct classes. `dataClass` is where the `#[DataTableColumn]` and
`#[DataTableFilter]` declarations are read. `entityClass` is what the runtime targets: Doctrine query
building, inline edit, bulk actions, row identifiers, Mercure topics and API Platform metadata.

Each one defaults to the other, so nothing changes for a table naming a single class —
`#[AsDataTable(User::class)]` and `#[AsDataTable(entityClass: User::class)]` both keep every feature
on `User`. What is new is passing the two together:

```php
use App\DataTables\Row\UserRow;
use App\Entity\User;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(dataClass: UserRow::class, entityClass: User::class)]
final class UserDataTable extends AbstractDataTable
{
    /**
     * @param list<User> $items
     *
     * @return list<UserRow>
     */
    protected function projectPage(array $items): ?array
    {
        return array_map(UserRow::fromEntity(...), $items);
    }
}
```

The attribute describes the shape, it does not build it: rows are still hydrated as `User`, so
`projectPage()` is what turns them into `UserRow`.

Two consequences when the two classes differ:

- Automatic query building checks `entityClass` for a Doctrine mapping, where it used to check the
  single declared class. A DTO `dataClass` is now legitimate.
- Ordering and searching still build DQL against `entityClass`, so a column or filter declared on the
  DTO must name a field the entity carries. The container checks this while it compiles and names the
  offender, instead of silently dropping the column from ordering and search. Dotted paths are left
  alone: they may target a join alias added in `customizeQueryBuilder()`.

  Name the DTO property after the entity field, or, when the two names must differ, redirect the query
  alone with the `searchField` option or an `orderExpression`. The `field` option is not the tool here:
  it redirects the displayed value as well, so a projected row reads nothing under it.

### Bulk actions (additive)

Tables can declare bulk actions, which the bundle runs over the rows the user selected:

```php
use Pentiminax\UX\DataTables\Model\BulkAction;
use Pentiminax\UX\DataTables\Model\BulkActions;
use Pentiminax\UX\DataTables\Mutation\BulkActionContext;
use Pentiminax\UX\DataTables\Mutation\BulkRecords;

public function configureBulkActions(BulkActions $actions): BulkActions
{
    return $actions->add(
        BulkAction::new('approve', 'Approve')
            ->askConfirmation('Approve {count} orders?')
            ->setPermission('ORDER_APPROVE', static fn (Order $order): Order => $order)
            ->handler(function (BulkRecords $records, BulkActionContext $context): void {
                foreach ($records as $order) {
                    $this->approver->approve($order);
                }
            })
    );
}
```

Nothing changes for a table that declares none. A table that declares one gains a checkbox
selection, a **Bulk actions** button above the table, and a `POST /datatables/ajax/bulk` route — protect it with your own
`security.firewalls` configuration like every other bundle Ajax route. See
[Bulk Actions](https://pentiminax.github.io/ux-datatables/features/bulk-actions/).

Two details are worth checking in an existing application:

- **A voter type-hinting `Action` may now receive a `BulkAction`.**
  `ActionPermissionContext::$action` is typed `ExecutableActionInterface`, which both `Action` and
  `BulkAction` implement. A voter that assumed `Action` should narrow explicitly:

  ```php
  // before
  protected function supports(string $attribute, mixed $subject): bool
  {
      return $subject instanceof ActionPermissionContext;
  }

  // after
  protected function supports(string $attribute, mixed $subject): bool
  {
      return $subject instanceof ActionPermissionContext && $subject->action instanceof Action;
  }
  ```

  This only matters once a table declares a bulk action.

- **Rows of a table with bulk actions always carry a `DT_RowId`.** It was previously written only
  for `highlightUpdates()`. The selection needs a key that survives the redraw server-side paging
  forces. The bundle detects a single Doctrine identifier automatically; configure another field
  with `BulkActions::setIdField()` when no Doctrine metadata is available.

A custom data provider can opt into "select every matching row" by implementing
`IdentifierCollectingDataProviderInterface`; `DoctrineDataProvider` already does. Its
`collectIdentifiers()` receives the field the selection speaks in — the one written as `DT_RowId` —
so it must answer with values from that field, not from the primary key. Without the interface, a
select-all is rejected with `400` while an explicit selection keeps working.

## v0.90 → v1.0

### The bundle class is renamed

The bundle class now follows Symfony's vendor-prefixed naming convention. Update its registration
in `config/bundles.php`:

```php
// before
Pentiminax\UX\DataTables\DataTablesBundle::class => ['all' => true],

// after
Pentiminax\UX\DataTables\PentiminaxDataTablesBundle::class => ['all' => true],
```

The old class is removed. Keeping it would register the bundle as `DataTablesBundle`, which is the
same logical name used by `omines/datatables-bundle` and prevents both packages from being enabled
in one application.

Update bundle resource references and Twig namespace references:

| Before | After |
| --- | --- |
| `@DataTablesBundle/config/routes.php` | `@PentiminaxDataTablesBundle/config/routes.php` |
| `@DataTables/...` | `@PentiminaxDataTables/...` |

The YAML configuration root stays `data_tables`; no configuration key changes are required.

### `AbstractDataTable::configureExtensions()` and the empty constructor removed

Declare extensions from `configureDataTable()` with the fluent helpers on `DataTable`. Use
`addExtension()` there for a custom extension that has no dedicated helper.

```php
// before
public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
{
    return $extensions->addExtension(new ButtonsExtension([ButtonType::CSV]));
}

// after
public function configureDataTable(DataTable $table): DataTable
{
    return $table->buttons([ButtonType::CSV]);
}
```

The empty `AbstractDataTable::__construct()` was also removed. Delete `parent::__construct()` from
subclass constructors; no replacement call is needed.

### `EntityMutator` and `EditFormService` take a `MutationFlusher`

The guarded flush that maps a rejected write to a 409 response lives in the new
`Mutation\MutationFlusher`. `EntityMutator` no longer owns it privately, and the edit-form submit
path now shares it, so a unique constraint violation on submit returns the same 409 JSON as delete
and inline edit instead of a 500.

| Changed | New signature |
| --- | --- |
| `Mutation\EntityMutator::__construct()` | a `Mutation\MutationFlusher` is appended after `$topicResolver` |
| `Form\EditFormService::__construct()` | a `Mutation\MutationFlusher` is inserted after `$topicResolver`, before the optional `$permissionChecker` |

Both services are wired by the bundle, so nothing changes for DI users. Only code that instantiates
either class by hand — typically a test — has to pass the collaborator:

```php
// before
$mutator = new EntityMutator($locator, $propertyAccessor, $publisher, $checker, $topicResolver);

// after
$mutator = new EntityMutator($locator, $propertyAccessor, $publisher, $checker, $topicResolver, new MutationFlusher());
```

`MutationFlusher` is stateless and has no constructor arguments.

### Changed

| Behavior | Before | After |
| --- | --- | --- |
| Permission check without a Symfony authorization checker | Silently granted | Throws a `LogicException` |

Configuring `setPermission()` without the Symfony authorization checker now throws a
`LogicException` instead of silently granting access. Enable `symfony/security-bundle` with a
firewall, or drop the permission.

The bundle's own `Permission::DT_*` attributes are unaffected: without the SecurityBundle the
bundle's `SecurityVoter` is not registered either, so there is nothing to vote on and an
application with no firewall keeps rendering its tables. An empty or `null` attribute — meaning
no permission is configured — is also still granted.

### Ajax pagination is bounded

Affects server-side tables whose Ajax requests relied on an unbounded page size. Table
configuration, columns, Twig templates, the Ajax routes, and every JSON payload on the wire are
unchanged.

The `length` parameter of an Ajax request is now capped at the `data_tables.max_page_length`
parameter (1000 by default), and a negative `start` is clamped to `0`.

DataTables' "show all" (`length=-1`, or a missing `length`) is honored only when the table declares
`-1` in its `lengthMenu()`. A table that relied on an implicit unbounded page must declare it, or
raise the bound:

```php
// before: any length was served, including "show all"
$table->lengthMenu([10, 25, 50]);

// after: declare "show all" to keep serving an unbounded page
$table->lengthMenu([[10, 25, 50, -1], ['10', '25', '50', 'All']]);
```

```yaml
# config/packages/data_tables.yaml
data_tables:
  max_page_length: 5000
```

Exports are unaffected: they drop pagination explicitly and still stream every filtered row.

### Malformed column control payloads are dropped

| Change | Impact |
| --- | --- |
| `ColumnControlSearch::fromArray()` returns `null` on malformed input | A column control search with an unknown or missing logic, a non-scalar value, or a missing type is dropped instead of raising an error. Malformed `search` and `list` payloads are dropped by `ColumnControl::fromArray()` the same way. |

### Client error messages and export headings

- Mutation errors no longer expose the DataTable class to the client. `InvalidBooleanMutationContextException`
  and `InvalidDataTableTokenException` keep the fully qualified class name in `getMessage()` for the
  logs, and the JSON error response now carries a generic message instead.
- Server-side exports no longer write the heading of a column the current user may not see. Columns
  denied by `setPermission()` are filtered out before the exporter runs, so the exported file no
  longer contains an empty column named after restricted data.

### API Platform frontend adapter

- The API Platform frontend adapter now matches ordering and column searches by column name instead
  of by display index, so a client-inserted column (the Select extension in `checkbox` mode) no
  longer sorts or searches on the wrong field. Unresolvable columns are skipped.
- A failed template rendering request no longer leaves the table spinning: rows are displayed
  unrendered instead.

### Secured API Platform properties are no longer auto-detected

Properties carrying `#[ApiProperty(security: …)]` are never auto-detected; declare them explicitly
and use `setPermission()`. API Platform evaluates that expression per request in its normalizer, and
an auto-detected column reads the value outside that check, so a table relying on auto-detection can
now be missing a column it used to render.

```php
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(Employee::class, apiPlatform: true)]
final class EmployeesDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        // Keep the auto-detected columns, then add the secured property yourself and guard it.
        yield from parent::configureColumns();
        yield TextColumn::new('salary')->setPermission('ROLE_ADMIN');
    }
}
```

### Case-insensitive server-side text search

Server-side text search (global search, per-column search, and the ColumnControl `contains`,
`starts`, `ends`, and `notContains` logics) is now case-insensitive on every platform: the
condition compares `LOWER(field)` against a lowercased term instead of using a bare `LIKE`, which
was case-sensitive on PostgreSQL and on MySQL binary collations.

Call `->setSearchNormalization(false)` on a column to restore the previous bare `LIKE` — for
instance to keep a `starts` search sargable on a MySQL prefix index. The comparison then follows
the column's collation, so it is case-sensitive only where the collation is:

```php
TextColumn::new('reference', 'Reference')
    ->setSearchNormalization(false);
```

`AbstractColumn` implements the new `Contracts\NormalizedSearchColumnInterface`. A column class
implementing `ColumnInterface` directly is normalized unless it implements that interface and
returns `false` from `isSearchNormalized()`.

### `ArrayDataProvider` honors ordering and search

`ArrayDataProvider` previously ignored ordering, per-column searches, ColumnControl and configured
`Filters`, and answered every such request with HTTP 200 and unfiltered rows. It now reads the same
`DataTableQueryIntent` the Doctrine provider consumes.

- The constructor takes three new optional arguments: the configured columns, the table's
  `Filters`, and a `DefaultDataTableQueryIntentFactory`. Existing
  two-argument constructions keep working, but with no columns nothing is orderable or searchable —
  pass `$this->getResolvedColumns()` (new, on `AbstractDataTable`) to get ordering and search, and
  the configured filters so a filtered request is rejected instead of silently ignored:

  ```php
  // Before
  return new ArrayDataProvider($this->rows, $this->createRowMapper());

  // After
  return new ArrayDataProvider(
      $this->rows,
      $this->createRowMapper(),
      $this->getResolvedColumns(),
      $this->getConfiguredDataTable()->getFilters(),
  );
  ```

- Global search now reads the **source** value of each item (the property named by the column's
  field path) instead of every scalar cell of the mapped row, matching the Doctrine semantics. It
  traverses nested arrays and objects, reads a backed enum's scalar value, honors
  `isGlobalSearchable()`, trims the term, and is case-insensitive. A
  column with no matching source property — an `ActionColumn`, a `TemplateColumn`, or a column whose
  value only exists after mapping — is no longer searchable in memory.
- Ordering compares the source value: strings with `strnatcasecmp()`, backed enums by their scalar
  value, other values with `<=>`, and rows without a value last in both directions.
  `getOrderExpression()` is DQL and is ignored in memory.
- A request carrying ColumnControl searches, or a value for one of the `Filters` passed to the
  constructor, now throws a `LogicException` instead of silently returning unfiltered rows. Filter
  values whose name is not configured are ignored, as the Doctrine provider ignores them. Implement
  `DataProviderInterface` yourself for a table that needs either in memory.

### API Platform tables with template columns are read server-side

A table that combines API Platform with a `TemplateColumn`, an action column or a `UrlColumn` used
to fetch the collection from the browser and then post the rows back to
`POST /datatables/ajax/templates` to have them rendered, one item provider call per row. It is now
an ordinary server-side table: the bundle reads the collection itself, through API Platform's main
state provider, once per draw. Tables without such a column keep querying the API directly from the
browser, unchanged.

| Behavior | Before | After |
| --- | --- | --- |
| Requests per draw | One collection request, then one `/datatables/ajax/templates` POST | One request to `ux_datatables_ajax_data` |
| Row loading | One API Platform `Get` item provider call per row | One collection provider call per page |
| Column values | Normalized JSON from the API response | Read from the entity, as for a Doctrine table |
| Exports | Doctrine query builder | The API Platform collection operation, with its provider, filters and `security` |
| Authorization | `security` on the `Get` item operation | `security` on the collection operation |

Consequences to check before upgrading:

- Move the authorization of these tables onto the collection operation. The `Get` operation is no
  longer consulted, and `access_control` rules matching the API path are not evaluated — the rows
  are read through a service call, not an HTTP request. Protect `/datatables/ajax/*` with your own
  `access_control` rule, as for any server-side table.
- Custom normalizers and property-level `security` no longer shape cell values. Declare columns only
  on properties every reader of the table may see.
- `recordsTotal` still equals `recordsFiltered`, as before.
- `DT_RowId` is now emitted by the server-side `RowIdStage` instead of the TypeScript adapter. The
  output is identical.

| Removed | Replacement |
| --- | --- |
| Route `ux_datatables_ajax_templates` (`/datatables/ajax/templates`) | none, the rows are rendered during the data request |
| `Controller\AjaxTemplateRenderController` | none |
| `Ajax\SourceRowResolver` | none |
| `Ajax\RowIdentifierExtractor` | none |
| `ApiPlatform\ApiPlatformItemResolver` | `DataProvider\ApiPlatformCollectionProvider`, which reads the collection |
| `Model\DataTable::apiPlatformTemplateRendering()` | `Model\DataTable::apiPlatformServerSide()`, set by the bundle |
| Services `datatables.ajax.source_row_resolver`, `datatables.controller.ajax_templates` | none |

### `entity` Twig variable removed from `TemplateColumn` templates

The alias was the last `@deprecated` symbol in the bundle, announced since v0.81.

| Removed | Replacement |
| --- | --- |
| `entity` Twig variable in `TemplateColumn` templates | `row` (`entity` stays a reserved context key, and the unrelated `entity` used by detail rows and the edit modal is untouched) |

### `MoneyColumn` duplicate setters removed

Four redundant setters duplicated the fluent names.

| Removed | Replacement |
| --- | --- |
| `Column\MoneyColumn::setCurrency()` | `currency()` |
| `Column\MoneyColumn::setNumDecimals()` | `decimals()` |
| `Column\MoneyColumn::setStoredAsCents()` | `storedAsCents()` |
| `Column\MoneyColumn::setShowCurrencySign()` | `showCurrencySign()` |

### `ExtensionInterface::enabled()`/`isEnabled()` and `AbstractExtension` removed

| Removed | Replacement |
| --- | --- |
| `Contracts\ExtensionInterface::enabled()` and `::isEnabled()` | none, extensions are always active once declared |
| `Model\Extensions\AbstractExtension` | `implements ExtensionInterface` directly; the class only re-declared `getKey()`, which the interface already requires |

The flag behind `ExtensionInterface::enabled()` was never read: `DataTableExtensions::jsonSerialize()`
omits only the buttons extension, so `enabled(false)` never kept anything out of the payload, and
`isEnabled()` reported `false` for extensions that were fully active.

### Fluent methods on non-final columns return `static`

Fluent methods on the non-final columns (`BooleanColumn`, `ChoiceColumn`, `DateColumn`,
`ActionColumn::fromActions()`) now return `static` instead of `self`, so a subclass keeps its own
type through a chain; code that re-narrowed the type by hand can drop the cast.

### `Filter` is renamed to `CheckboxFilter`

The generic, query()-driven checkbox filter was named `Filter`, the same word as the `FilterInterface`
contract and the `Filter\` namespace it lives in. It is renamed to match its sibling filter types
(`TextFilter`, `ChoiceFilter`, `TernaryFilter`, `DateRangeFilter`).

| Removed | Replacement |
| --- | --- |
| `Filter\Filter` | `Filter\CheckboxFilter` |

### `template_parameters` configuration is renamed to `table_attributes`

The `data_tables.template_parameters` configuration node held HTML attributes for the generated
`<table>` element (`class`, ...); it was never related to `TemplateColumn`'s own parameters. It is
renamed to describe what it actually configures.

```yaml
# before
data_tables:
  template_parameters:
    class: 'table table-striped'

# after
data_tables:
  table_attributes:
    class: 'table table-striped'
```

### `DataTableExtensions::add*Extension()` helpers removed

Six undocumented helpers duplicated `DataTable::buttons()`/`responsive()`/`columnControl()` and
`addExtension(new XExtension())` for the rest.

| Removed | Replacement |
| --- | --- |
| `Model\DataTableExtensions::addButtonsExtension()` | `DataTable::buttons()` |
| `Model\DataTableExtensions::addResponsiveExtension()` | `DataTable::responsive()` |
| `Model\DataTableExtensions::addColumnControlExtension()` | `DataTable::columnControl()` |
| `Model\DataTableExtensions::addSelectExtension()` | `DataTable::select()` |
| `Model\DataTableExtensions::addKeyTableExtension()` | `DataTable::keyTable()` |
| `Model\DataTableExtensions::addScrollerExtension()` | `DataTable::scroller()` |

`DataTable` now carries a shortcut for every bundled extension: `select()`, `keyTable()`,
`scroller()`, `colReorder()`, `fixedColumns()`, `fixedHeader()` and `rowGroup()` join the existing
`buttons()`, `responsive()` and `columnControl()`. Each mirrors the parameter names and defaults of
the matching extension constructor, so extensions are configurable with named arguments straight
from `configureDataTable()`. `responsive()` gained the `ResponsiveExtension` constructor parameters;
calling it without arguments is unchanged, and `addExtension(new XExtension(...))` keeps working.

### `BaseColumnData` (TypeScript) removed

| Removed | Replacement |
| --- | --- |
| `BaseColumnData` (TypeScript) | none, it was exported but never used or re-exported |

### `FilterInterface` declares `translateLabels()`

`TranslatableFilterInterface` held a single method and a single implementation. It is gone: filters
now declare `translateLabels(TranslatorInterface $translator, ?string $locale = null): void` on
`FilterInterface` itself, and `RenderingPreparer` calls it on every configured filter instead of
testing for the optional interface first. Filters extending `AbstractFilter` need no change.

A custom filter implementing `FilterInterface` directly must add the method; an empty body is a
valid implementation when the filter exposes no translatable string.

| Removed | Replacement |
| --- | --- |
| `Contracts\TranslatableFilterInterface` | `Contracts\FilterInterface::translateLabels()` |

### `DataTableInfrastructure::$queryIntentFactory` is removed

The property was promoted to public API in v0.83 alongside `columnResolver`, `renderingPreparer`,
`runtimeFactory`, `queryFilterPipeline`, and `profiler`, but unlike its siblings it was never read
by `AbstractDataTable` or anything else -- `ArrayDataProvider` and `QueryFilterPipeline` each build
or receive their own `DefaultDataTableQueryIntentFactory` independently. It stays unread even after
`ArrayDataProvider` started consuming `DataTableQueryIntent` (v0.90), so it is removed.

```php
// before
new DataTableInfrastructure($columnResolver, $renderingPreparer, $runtimeFactory, $queryIntentFactory, $queryFilterPipeline, ...);

// after
new DataTableInfrastructure($columnResolver, $renderingPreparer, $runtimeFactory, $queryFilterPipeline, ...);
```

`DataTableInfrastructure::createDefault()` keeps its `queryIntentFactory` parameter unchanged: it is
still used to build the default `QueryFilterPipeline` when none is passed, it just stops being
stored on the resulting object.

| Removed | Replacement |
| --- | --- |
| `DataTableInfrastructure::$queryIntentFactory` | none -- construct your own `DefaultDataTableQueryIntentFactory` where you need one |

### `ColumnInterface` narrowed to the getters the bundle consumes

| Removed | Replacement |
| --- | --- |
| `Contracts\ColumnInterface::getWidth()`, `::getCellType()`, `::getDefaultContent()` | none on the interface; `AbstractColumn` keeps both the setters and the getters, and all three still serialize into the client payload |

### Two private Ajax service ids renamed

DetailRowService and SourceRowResolver both live in Ajax\, so their
service ids are renamed to match. Both are private, so there is no BC
guarantee, but a compiler pass or `#[AsDecorator]` referencing the old id
by name would fail at container compile time.

| Renamed service id | New id |
| --- | --- |
| `datatables.detail.row_service` | `datatables.ajax.detail_row_service` |
| `datatables.rehydration.source_row_resolver` | `datatables.ajax.source_row_resolver` |

### Mercure subscriptions follow the hub's protocol version

The browser now selects topics with the query parameter the configured hub expects, read from
`HubInterface::getProtocolVersion()` (`symfony/mercure` 0.8+):

| Hub | Subscription parameters |
| --- | --- |
| 0.x | `topic=` with URI Template selectors — unchanged |
| 1.0 | `match=` (exact) and `match_urlpattern=` (URL Pattern, `/books/{id}` becomes `/books/:p0`) |

Nothing changes for a 0.x hub, down to the serialized payload: `protocolVersion` is only added to the
frontend `mercure` object when it is not `0.x`. A hub whose protocol version cannot be read (an older
`symfony/mercure` without the getter) keeps the legacy `topic=` parameters.

Before this release a Mercure 1.0 hub could not deliver anything: it ignores `topic=`, so the SSE
connection opened and stayed silent. If you run a 1.0 hub, this release is what makes live updates
work — and if you run a 1.0 hub with `protocol_version_compatibility 8` while migrating other
clients, both spellings are accepted and nothing needs doing.

Two things to check on the hub side when moving to 1.0: `jwt.claims` is required by RFC 9068 (a 1.0
hub refuses to compile without it), and the subscriber cookie becomes `__Secure-mercure_access_token`,
so private topics need `withCredentials: true` on the table **and** an HTTPS hub URL.

### Mercure auto-resolved topics are absolute

An auto-resolved topic now carries the absolute URL API Platform publishes, built from the routing
request context (scheme, host, port, front-controller base path) exactly like the URL generator the
publisher uses:

| Hub protocol | Topic before | Matched the published IRI? | Topic now |
| --- | --- | --- | --- |
| 1.0, hub sharing the API's host | `/api/books/{id}` | yes | `https://api.example.com/api/books/{id}` |
| 1.0, hub on another host | `/api/books/{id}` | no | `https://api.example.com/api/books/{id}` |
| 0.x, any host | `/api/books/{id}` | no | `https://api.example.com/api/books/{id}` |

The two protocols failed for different reasons, and 0.x failed everywhere:

- A **1.0** hub matches URL Patterns *with its own URL as the base URL*, so a relative pattern
  became `https://<hub-host>/api/books/{id}` and only ever covered the published IRI while the hub
  and the API shared a host. An absolute pattern is matched as-is, so no base applies and no host
  has to line up. (A 1.0 hub with no public URL or `resource_identifier` configured falls back to
  an internal `http://mercure.invalid` base, under which a relative pattern could never match an
  absolute topic on any host.)
- A **0.x** hub compares the `topic=` selector to the published topic exactly, then as an anchored
  URI Template — no base resolution anywhere. A relative selector could therefore never match the
  absolute IRI API Platform publishes, on *any* host. If you run a 0.x hub, auto-resolved API
  Platform topics never delivered anything before this release.

What else changed in the same pass:

- A plain topic declared in `mercure: ['topics' => [...]]` is still reused verbatim. Declaring the
  absolute form stays the escape hatch for a topic of your own.
- `'@=iri(object)'` is recognised explicitly and resolves to the item topic. Every other `@=`
  expression topic is dropped with a logged warning naming it. The resolver still falls back to the
  item path afterwards, so a resource whose only topic is `@=iri(object.getOwner())` still
  subscribes to the item topic — the warning is what tells you the declared topic was not honored.
- The item topic is read off the operation API Platform itself builds the item IRI from: the first
  non-collection operation whose HTTP method is GET, HEAD or OPTIONS, in declaration order. A
  custom `HttpOperation(method: 'GET')` counts; a resource with no such operation has no item topic
  at all, because API Platform has no item IRI for it either and throws rather than publishing one.
- A topic declared on a *later* operation now wins over the item path. Before, the first
  non-collection operation returned its route path immediately and a declared topic behind it was
  never reached, so a resource declaring `Get('/books/{id}')` then
  `Put(mercure: ['topics' => [...]])` serialized `/api/books/{id}`; it now serializes the declared
  topic. Declared topics are documented as authoritative, so this aligns the code with the docs —
  but it does change the serialized `mercure.topics` payload for that shape.
- **Only the API Platform item topic became absolute.** The bundle's own
  `/datatables/{plural}/{id}` fallback topic stays relative on purpose: nothing but the bundle
  publishes to it, and it publishes through the same resolver, so a context-free topic keeps the
  two sides matching wherever they run.
- Without a router at all, the item topic stays relative. With a router — which a full-stack
  application always has — the context is whatever the application configured. In a console
  command or a Messenger consumer that is `router.request_context`, defaulting to
  `http://localhost`, so **set `router.request_context.host` and `.scheme` if anything outside an
  HTTP request publishes**, exactly as you already must for API Platform's own IRIs to come out
  right there.

One constructor gained optional arguments, appended last:

| Changed | Appended argument |
| --- | --- |
| `ApiPlatform\ApiResourceMercureMetadataResolver::__construct()` | `?Mercure\MercureTopicUrlResolver`, then `?Psr\Log\LoggerInterface` |

### Private API Platform resources subscribe with credentials

API Platform marks an update private from resource metadata
(`#[ApiResource(mercure: ['private' => true])]`), and a hub delivers such an update only to a
subscriber whose token grants one of the update's topics. The bundle did not read the flag, so the
browser opened the `EventSource` anonymously: the connection stayed up and the table silently
stopped refreshing.

`ApiPlatform\ApiResourceMercureMetadataResolver::resolvePrivate()` now reads the flag from the
resource metadata and from every operation, and `Mercure\MercureConfigResolver` serializes
`withCredentials: true` as soon as one of them declares `private`. Nothing to configure: a table
whose resource is private starts sending credentials, and a resource that does not set `private`
(or sets `private: false`) serializes the exact payload it did before.

An explicit `withCredentials` still wins, on both `#[AsDataTable(..., mercure: [...])]` and
`->mercure()`. On the attribute, an array without a `topics` key auto-resolves the topics and only
overrides the subscription options — the previous release required spelling the topics out, and
threw *Mercure topics cannot be empty.* without them:

```php
#[AsDataTable(Book::class, mercure: ['withCredentials' => false])]
```

`->mercure(withCredentials: false)` is manual configuration: it never reaches auto-resolution, so it
subscribes to the bundle's internal fallback topic instead of the API Platform one unless the topics
are passed with it.

The table then stops refreshing on private updates, which is what that setting asks for.

The two application-side prerequisites are unchanged and are now documented in
`docs/src/content/docs/integrations/mercure.mdx` (*Private Topics*): the subscriber cookie is
symfony/mercure-bundle's job, and its grant must cover the concrete published topic — under Mercure
1.0 `match_type` defaults to `exact`, so a 0.x grant holding `/api/books/{id}` does not cover
`/api/books/42`. On a 1.0 hub the cookie is `__Secure-mercure_access_token`, so the hub URL must be
HTTPS.

### `#[AsDataTable]` attribute finalized ahead of 1.0

Nothing to do for a table that already declares its attribute with a well-formed entity class and an
explicit `topics` list. The changes below all turn a later, less legible failure into an immediate
one, or make two switches behave the same.

| Behavior | Before | After |
| --- | --- | --- |
| `editModalTemplate` / `editModalAdapter` default | `''` (the "unset" sentinel) | `null` |
| `entityClass` typo, or an interface | Failed later, inside Doctrine metadata or API Platform | `InvalidArgumentException` where the attribute is read |
| `mercure` array without `topics` | `InvalidArgumentException: Mercure topics cannot be empty.` | Topics come from the auto-resolver, the declared options are applied on top |
| Unknown or wrongly typed `mercure` option | The same empty-topics exception | `InvalidArgumentException` naming the option, the attribute and the entity class |
| `$table->apiPlatform()` | Enabled the Ajax wiring and the frontend adapter, but not column auto-detection | Equivalent to `apiPlatform: true` on the attribute |

The two `editModal*` arguments stay backward compatible in practice: an empty string still means
"no override", so existing values keep working and only the declared default changes.

```php
// before: auto topics could not be combined with a custom debounce
#[AsDataTable(Book::class, mercure: ['debounceMs' => 250])] // Mercure topics cannot be empty.

// after
#[AsDataTable(Book::class, mercure: ['debounceMs' => 250])] // auto topics + 250 ms debounce
```

`serializationGroups` is documented as requiring the API Platform opt-in, and the attribute is
documented as not inherited: `#[AsDataTable]` on an abstract base class gives its subclasses no
attribute at all, so every concrete table class needs its own.

One collaborator was added, and two signatures grew a trailing argument. The bundle wires all three,
so only hand-instantiated code is affected:

| Changed | Appended argument |
| --- | --- |
| `Runtime\DataTableInfrastructure::__construct()` | `AsDataTableResolver` |
| `Runtime\DataTableInfrastructure::createDefault()` | `?AsDataTableResolver` |
| `Form\EditModalTemplateResolver::__construct()` | `AsDataTableResolver` |
| `Column\ColumnResolver::resolveColumns()` / `::autoDetectColumns()` | `bool $apiPlatform = false`, the table's fluent opt-in |

`Attribute\AsDataTableResolver` is the new single reflection entry point for the attribute
(`datatables.attribute.resolver`); `AbstractDataTable` and `EditModalTemplateResolver` both resolve
through it instead of keeping a private static cache each.

## v0.84 → v0.85

### Permission configuration uses `setPermission()`

The fluent `permission()` method on columns and actions has been renamed to `setPermission()`.
This is an intentional breaking rename: update every `->permission(...)` call before upgrading.

```php
// before
TextColumn::new('salary')->permission('ROLE_ADMIN');

// after
TextColumn::new('salary')->setPermission('ROLE_ADMIN');
```

Permissions also accept `Symfony\Component\ExpressionLanguage\Expression` objects. Install the
optional component in applications that use expressions:

```bash
composer require symfony/expression-language
```

Direct `ColumnInterface` implementations do not need to add `setPermission()`; the setter is
provided by `AbstractColumn`. Their existing `getPermission()` implementation remains valid for
string permissions. Return `string|Expression|null` when a custom column needs to expose an
expression permission.

### Search configuration changes

No source change is required. Columns extending `AbstractColumn` — every bundled column type and
any subclass of one — gain the new search configuration automatically, and a class implementing
`Contracts\ColumnInterface` directly keeps working unchanged. Two runtime behaviors around
server-side search did change; read the second section if a table searches a column whose value is
assembled in `mapRow()`, or if `customizeQueryBuilder()` joins a relation a column also searches.

### Columns can declare how they are searched

Server-side search resolves a column to `<alias>.<field>`. A column whose value is assembled in
`mapRow()` had nowhere valid to point, so Doctrine rejected the whole query with `has no field or
association named ...` — taking the table down rather than that one column. Ordering already had
`setOrderExpression()` for this; search had nothing.

`Contracts\SearchableColumnInterface` is the new opt-in contract for it, alongside
`TemplateAwareColumnInterface` and `ActionsProvidingColumnInterface`:

| Member | Contract |
| --- | --- |
| `getSearchField(): ?string` | Field path to search instead of `getField()`, or `null` to search the displayed field |
| `getSearchJoins(): array` | `list<array{join: string, alias: string, conditionType: ?string, condition: ?string}>` applied before the predicate is built |
| `buildSearchPredicate(QueryBuilder $qb, string $alias, string $value, string $paramName): ?string` | Custom DQL condition, or `null` to fall back to the type-based predicate |

`AbstractColumn` implements all three and exposes them as fluent setters, so there is nothing to do
for a column that extends it or one of the bundled types:

```php
TextColumn::new('donorProviderName', 'Donor')
    ->setSearchField('donorProvider.name');

TextColumn::new('recipientProviderName', 'Recipient')
    ->addSearchJoin('e.recipientProvider', 'rp')
    ->setSearchField('rp.name');

TextColumn::new('dossierId', 'Dossier')
    ->setSearchPredicate(function (QueryBuilder $qb, string $alias, string $value, string $paramName): string {
        $qb->setParameter($paramName, '%'.mb_strtolower($value).'%');

        return \sprintf('LOWER(%s.dossierId) LIKE :%s', $alias, $paramName);
    });
```

`ColumnInterface` is untouched. A class implementing it directly is searched exactly as before —
`getField()`, no extra joins — and implements `SearchableColumnInterface` only if it wants the
override.

`buildSearchPredicate()` is the one column member that is not an accessor. It runs only at the
Doctrine query boundary, once per search term. Bind parameters on the query builder — under
`$paramName` or names derived from it — and **return** the condition instead of calling
`andWhere()`: global search combines the returned conditions with `OR`, a per-column search with
`AND`.

Only global search and per-column search consult `buildSearchPredicate()`. ColumnControl's list,
comparison, and empty/not-empty logics honor `getSearchField()` and `getSearchJoins()` but keep
their own predicate shapes.

### An unmapped searchable column is skipped instead of erroring

`Query\RelationFieldResolver::supportsSearchFiltering()` now returns `false` for a bare field the
root entity maps neither as a scalar nor as an association. Such a field is a virtual column
assembled in `mapRow()`, and emitting `<alias>.<field>` for it made Doctrine reject the entire
query.

A table that previously returned a 500 on search now answers normally, with that one column left
out of the predicate — so its search box silently matches nothing. Point it at real data with
`setSearchField()`, `addSearchJoin()`, or `setSearchPredicate()`, or opt out with
`setSearchable(false)` and `disableGlobalSearch()`.

`Query\RelationFieldResolver::resolve()` also reuses a join already registered for the same
expression under an alias of your choosing, instead of joining the relation a second time under the
alias it derives itself. A `customizeQueryBuilder()` that joined `e.author` as `a` and a column
searching `author.name` now produce one join and the predicate reads `a.name`, where before there
were two joins and the predicate read `author.name`.

Finally, the metadata lookups behind these guards no longer swallow every exception. They treat a
root class Doctrine does not map as "no metadata" and let every other failure propagate — a mapping
driver rejecting an attribute, or an unreachable metadata cache, now surfaces where it used to
become a silently unsearchable column.

## v0.83 → v0.84

Affects applications that built tables with `DataTableBuilderInterface` in a controller, passed a
bare `DataTable` to `render_datatable()`, or constructed `DataTableInfrastructure` themselves; code
that implemented or type-hinted one of the removed single-implementation interfaces, custom columns
implementing `ColumnInterface` directly, or callers of `Query\SearchPredicateFactory`; and code that
decorates or hand-instantiates `EntityMutator` or `EditFormService`, called
`MercureTopicResolver::resolve()` statically, decorates or hand-instantiates
`Twig\DataTablesExtension`, or used `RowMapper\ClosureRowMapper`; and any code that imports one
of the classes listed under [Moved classes](#moved-classes).
Tables already declared as `AbstractDataTable` classes are unchanged, as are columns, filters, Twig
templates, the Ajax routes, and every JSON payload on the wire.

### `DataTableBuilderInterface` and `DataTableBuilder` are removed

There is now exactly one way to define a table: a class extending `AbstractDataTable`. The Twig
function `render_datatable()` accepts only an `AbstractDataTable`; passing a bare `DataTable` throws
a `TypeError`.

`DataTableInfrastructure` carries the `data_tables` bundle defaults itself. Its `builder()` method
is gone, replaced by `createDataTable(string $id)`, and its constructor takes the defaults as three
arrays (`$options`, `$attributes`, `$extensions`) where the builder used to sit.

The table ID is the short name of the table class, so `UsersDataTable` renders as
`<table id="UsersDataTable">`. Two tables on the same page must not share a class name.

```php
// before
namespace App\Controller;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataTableBuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'app_users')]
    public function index(DataTableBuilderInterface $builder): Response
    {
        $table = $builder
            ->createDataTable('usersTable')
            ->columns([
                TextColumn::new('firstName', 'First name'),
                TextColumn::new('lastName', 'Last name'),
            ])
            ->data([
                ['firstName' => 'John', 'lastName' => 'Doe'],
            ]);

        return $this->render('user/index.html.twig', ['table' => $table]);
    }
}
```

```php
// after
namespace App\DataTables;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

final class UsersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('firstName', 'First name');
        yield TextColumn::new('lastName', 'Last name');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([
            ['firstName' => 'John', 'lastName' => 'Doe'],
        ]);
    }
}
```

```php
// after
namespace App\Controller;

use App\DataTables\UsersDataTable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'app_users')]
    public function index(UsersDataTable $table): Response
    {
        return $this->render('user/index.html.twig', ['table' => $table]);
    }
}
```

When the rows are only known at request time, keep `configureDataTable()` for the options and call
`$table->setData($rows)` in the controller.

### Single-implementation interfaces are removed

Each of these interfaces had exactly one implementation. Type-hint the concrete class instead; it is
no longer `final`, so a test double or a subclass of your own still works.

| Removed interface | Type-hint instead |
| --- | --- |
| `Contracts\ColumnAutoDetectorInterface` | `ApiPlatform\ColumnAutoDetector` |
| `Contracts\EditModalTemplateResolverInterface` | `Form\EditModalTemplateResolver` |
| `Contracts\PermissionAwareColumnInterface` | `Contracts\ColumnInterface` |
| `Contracts\LayoutAwareExtensionInterface` | `Model\Extensions\ButtonsExtension` |

`PermissionAwareColumnInterface` was a one-method contract on top of `ColumnInterface`, and every
column already carried it. `getPermission(): ?string` now lives on `ColumnInterface` itself, so an
`instanceof` check before reading it is no longer needed. Columns extending `AbstractColumn` are
unaffected. A class of yours implementing `ColumnInterface` directly must add the method; return
`null` when the column is always visible.

`LayoutAwareExtensionInterface` was a marker for extensions injected into the DataTables `layout`
configuration rather than serialized as top-level options. `ButtonsExtension` is the only
layout-aware extension, so the marker is gone and the two call sites test for that class directly.
The profiler's `layoutAware` flag keeps its name and meaning.

The service aliases for the two resolver interfaces are gone. `ApiPlatform\ColumnAutoDetector` is
aliased in its place, so an autowired argument keeps resolving; point a decoration or an explicit
argument at the concrete class. `Form\EditModalTemplateResolver` has no alias: reference the
`datatables.form.edit_modal_template_resolver` service id.

### `SearchPredicateFactory` is merged into `DefaultSearchPredicateBuilder`

`Query\SearchPredicateFactory` was a static one-method class that
`Query\DefaultSearchPredicateBuilder` forwarded to unchanged. The type-dispatch logic now lives in
the builder, and the factory is removed.

```php
// before
$predicate = SearchPredicateFactory::build($qb, $column, $alias, $field, $value, $paramName, $forceNumeric);

// after
$predicate = (new DefaultSearchPredicateBuilder())->build($qb, $column, $alias, $field, $value, $paramName, $forceNumeric);
```

`Query\Strategy\ContainsSearchStrategy` now takes the builder as an optional constructor argument
defaulting to `DefaultSearchPredicateBuilder`, so `new ContainsSearchStrategy()` keeps working.

`SearchPredicateBuilderInterface` is untouched: it stays the supported seam for the
`AbstractDataTable::createSearchPredicateBuilder()` hook, and a custom builder of yours keeps
working.

### `MercureTopicResolver` is a service

`MercureTopicResolver` is no longer a `final` class with a static `resolve()`. It is an instantiated
service (`datatables.mercure.topic_resolver`) that receives the optional `MercureConfigResolver` and
the `datatables.data_table` service locator once, in its constructor.

```php
// before
$topics = MercureTopicResolver::resolve($configResolver, $entityClass, $dataTables, $dataTableClass);

// after
$topics = $topicResolver->resolve($entityClass, $dataTableClass);
```

`EntityMutator` and `EditFormService` now take that resolver instead of the
`?MercureConfigResolver` + `?Psr\Container\ContainerInterface` pair they used to forward to the
static call:

```php
// before
new EntityMutator($locator, $propertyAccessor, $publisher, $permissionChecker, $configResolver, $dataTables);
new EditFormService($locator, $builder, $renderer, $templateResolver, $publisher, $configResolver, $dataTables, $permissionChecker);

// after
new EntityMutator($locator, $propertyAccessor, $publisher, $permissionChecker, $topicResolver);
new EditFormService($locator, $builder, $renderer, $templateResolver, $publisher, $topicResolver, $permissionChecker);
```

The positional service arguments changed accordingly: `datatables.mutation.mutator` takes the topic
resolver as `arg(4)` and no longer has an `arg(5)`, and `datatables.form.edit_form_service` takes it
as `arg(5)` with the permission checker moving to `arg(6)`. `delete()`, `setProperty()`,
`handleView()` and `handleSubmit()` are unchanged.

The service is declared in `config/services.php` rather than `config/mercure.php`, so mutations
still resolve topics (to an empty list) when Mercure is not installed.

### Inline rows are rendered once, by the row pipeline

`render_datatable()` no longer re-renders `TemplateColumn`s, resolves actions, or strips denied
column values on inline rows. `AbstractDataTable` already does all of it exactly once, through the
row pipeline, whichever way the rows arrive: `configureDataTable()->data()`, `setData()`, or
client-side hydration. Rendering a table twice in one request no longer risks double-rendering a
template column.

The `DataTablesExtension` constructor lost its `TemplateColumnRenderer` and `ActionRowDataResolver`
arguments; `ColumnResolver` moves from `arg(3)` to `arg(1)`, and every following argument shifts down
by two. This matters only to code that decorates or hand-instantiates the extension. The rendered
payload is unchanged.

`RowMapper\ClosureRowMapper` is removed; it had no consumer in the bundle. Implement
`RowMapperInterface` yourself when you need a closure-backed mapper:

```php
use Pentiminax\UX\DataTables\Contracts\RowMapperInterface;

$mapper = new class($fn) implements RowMapperInterface {
    public function __construct(private readonly \Closure $fn) {}

    public function map(mixed $item): array { return ($this->fn)($item); }
};
```

### Moved classes

`src/` had several folders holding a single class, away from its only consumer. The folder tree now
matches the bounded contexts documented in `AGENTS.md`. These are pure namespace moves: the classes,
their methods, and their behavior are unchanged. There is no class alias and no deprecation layer,
so update your imports.

| Old FQCN | New FQCN |
| --- | --- |
| `DataCollector\DataTableCollector` | `Profiler\DataTableCollector` |
| `Rendering\RenderingPreparer` | `Runtime\RenderingPreparer` |
| `Dto\AjaxEditFormRequestDto` | `Controller\AjaxEditFormRequestDto` |
| `Dto\AjaxEditRequestDto` | `Controller\AjaxEditRequestDto` |
| `Dto\AjaxEntityQueryDto` | `Controller\AjaxEntityQueryDto` |
| `Detail\DetailRowService` | `Ajax\DetailRowService` |
| `Rehydration\RowIdentifierExtractor` | `Ajax\RowIdentifierExtractor` |
| `Rehydration\SourceRowResolver` | `Ajax\SourceRowResolver` |
| `Query\Intent\InvalidQueryIntentException` | `Exception\InvalidQueryIntentException` |
| `Export\ExporterInterface` | `Contracts\ExporterInterface` |
| `Mercure\MercurePublisherInterface` | `Contracts\MercurePublisherInterface` |

Namespaces are relative to `Pentiminax\UX\DataTables\`. The emptied `DataCollector`, `Rendering`,
`Dto`, `Detail`, and `Rehydration` namespaces no longer exist.

Two of these are supported extension seams, so they matter beyond a search-and-replace:

- `Contracts\MercurePublisherInterface` is still the seam for replacing how updates are published.
  If you implement it or alias it in your container, change the import; the `publish()` signature is
  identical.
- `Contracts\ExporterInterface` is still the seam for custom export formats. If you implement it,
  change the import; `format()`, `isAvailable()`, and `write()` are identical.

## v0.82 → v0.83

Affects custom `QueryFilterInterface` implementations, code that implemented one of the removed
interfaces, and code that touched `DataTableInfrastructure`, the Ajax result types, or the internal
query/row-pipeline classes listed below. Table configuration, columns, Twig templates, the Ajax
routes, and every JSON payload on the wire are unchanged. If your project only configures tables
through `AbstractDataTable`, there is nothing to do.

### `DataTableQueryIntent` is flat

The per-concern intent DTOs are gone. A custom `QueryFilterInterface` reads the same values
directly off `$context->intent`.

```php
// before
$offset    = $context->intent->pagination->offset;
$limit     = $context->intent->pagination->limit;
$search    = $context->intent->globalSearch?->value;
$column    = $context->intent->order?->column;
$direction = $context->intent->order?->direction->value;

foreach ($context->intent->columnSearches as $columnSearch) {
    $name  = $columnSearch->column->getName();
    $value = $columnSearch->value;
}

// after
$offset    = $context->intent->offset;
$limit     = $context->intent->limit;
$search    = $context->intent->globalSearch;
$column    = $context->intent->orderColumn;
$direction = $context->intent->orderDir;

foreach ($context->intent->columnSearches as $columnSearch) {
    $name  = $columnSearch['column']->getName();
    $value = $columnSearch['value'];
}
```

What changed:

- `PaginationIntent` is inlined as `offset` (`int`) and `limit` (`?int`)
- `GlobalSearchIntent` is inlined as `globalSearch` (`?string`); an absent search is `null`
- `OrderIntent` is inlined as `orderColumn` (`?ColumnReadReference`) and `orderDir`
  (`'asc'|'desc'|null`, a plain string instead of the `SortDirection` enum). `SortDirection` is
  removed; compare against `'asc'`/`'desc'`
- `ColumnSearchIntent` is replaced by `array{column: ColumnReadReference, value: string}`; read
  array keys instead of properties
- `regexRequested` is gone from both search shapes. The bundle's own filters never applied it. If a
  filter of yours read it, resolve regex intent from `DataTableRequest` before the query pipeline
  runs
- `draw`, `columns`, and `columnControls` are unchanged

### `DataTableQueryIntentFactoryInterface` is removed

Intent creation is no longer an extension point. `DefaultDataTableQueryIntentFactory` is the
concrete collaborator, and `QueryFilterPipeline` depends on it directly.

- an autowired `DataTableQueryIntentFactoryInterface` argument no longer resolves; type-hint
  `DefaultDataTableQueryIntentFactory`
- a class of yours implementing the interface must be dropped. Express request-shaping in a
  `QueryFilterInterface` implementation instead, which stays a public contract

### `DataTableInfrastructure` accessors are properties

The collaborator getters are gone; the promoted properties are public readonly.

```php
// before
$infrastructure->columnResolver();
$infrastructure->queryFilterPipeline();

// after
$infrastructure->columnResolver;
$infrastructure->queryFilterPipeline;
```

`columnResolver`, `renderingPreparer`, `runtimeFactory`, `queryIntentFactory`,
`queryFilterPipeline`, and `profiler` are affected. `builder()` stays a method, because it lazily
creates the builder.

### Removed internal classes

These had a single consumer each and are inlined into their caller. None of them is a documented
extension point, and none had a public service alias.

| Removed | Replacement |
| --- | --- |
| `Query\Builder\QueryFilterChain` | `Query\Builder\QueryFilterPipeline`, which owns the filter order and the `resetParamIndexScope()` call between filters |
| `DataProvider\DataProviderResolver` | `DataProvider\AutoDataProviderFactory`, injected straight into `DataTableRuntimeFactory` |
| `RowMapper\Stage\UrlColumnResolutionStage` | `RowProcessingPipeline` calls `UrlColumnDataResolver` |
| `RowMapper\Stage\TemplateRenderingStage` | `RowProcessingPipeline` calls `TemplateColumnRenderer` |
| `RowMapper\Stage\ActionResolutionStage` | `RowProcessingPipeline` calls `ActionRowDataResolver` |

`RowStageInterface` and `RowProcessingPipeline::add()` are untouched: a custom stage of yours keeps
working. Only these three built-in stage classes disappear, and the behavior they performed still
runs in the same order inside the pipeline.

### Mercure and API Platform resolver interfaces are removed

Each of these interfaces had exactly one implementation. Type-hint the concrete class instead; it is
no longer `final`, so a test double or a subclass of your own still works.

| Removed interface | Type-hint instead |
| --- | --- |
| `Mercure\MercureConfigResolverInterface` | `Mercure\MercureConfigResolver` |
| `Mercure\MercureHubUrlResolverInterface` | `Mercure\MercureHubUrlResolver` |
| `Mercure\ApiResourceMercureMetadataResolverInterface` | `ApiPlatform\ApiResourceMercureMetadataResolver` |
| `ApiPlatform\ApiResourceCollectionUrlResolverInterface` | `ApiPlatform\ApiResourceCollectionUrlResolver` |

The service aliases move with them, so an autowired argument or a service decoration targeting one
of the interfaces no longer resolves. Point it at the concrete class.

`MercurePublisherInterface` is untouched: it is the supported seam for replacing how updates are
published, and a custom publisher of yours keeps working.

### Ajax result and request DTOs are collapsed

`DetailRowResult` and `EditFormResult` were the same four-property type with the same factories, and
each Ajax controller rebuilt the JSON by hand. One result type now owns both.

```php
// before
use Pentiminax\UX\DataTables\Detail\DetailRowResult;
use Pentiminax\UX\DataTables\Form\EditFormResult;
use Pentiminax\UX\DataTables\Http\JsonErrorResponse;

$result = $detailRowService->handleView($dataTable, $id);          // DetailRowResult

if (!$result->success) {
    return JsonErrorResponse::create($result->message, $result->statusCode);
}

return new JsonResponse(['success' => true, 'html' => $result->html]);

// after
use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;

$result = $detailRowService->handleView($dataTable, $id);          // AjaxActionResult

return $result->toJsonResponse();
```

What changed:

- `Detail\DetailRowResult` and `Form\EditFormResult` are replaced by `Ajax\AjaxActionResult`, with
  the same `success`, `html`, `message`, and `statusCode` properties and the same
  `success()`/`badRequest()`/`invalid()`/`notFound()`/`forbidden()` factories
- `DetailRowService::handleView()`, `EditFormService::handleView()`, and
  `EditFormService::handleSubmit()` return `AjaxActionResult`
- `Http\JsonErrorResponse` is removed. `AjaxActionResult::toJsonResponse()` builds the response,
  error cases included
- `Dto\AjaxDetailQueryDto` is renamed `Dto\AjaxEntityQueryDto`, and `Dto\AjaxEditFormQueryDto` and
  `Dto\AjaxDeleteRequestDto` are removed in favor of it. The three routes always mapped the same
  `{dataTable, id}` body. `Dto\AjaxEditRequestDto` and `Dto\AjaxEditFormRequestDto` are unchanged

The Ajax JSON is byte-for-byte what it was: same routes, same keys, same status codes. Frontend
code, including a custom fetch of your own against these routes, needs no change.

## v0.80 → v0.81

Only `TemplateColumn` is affected. If you do not use it, there is nothing to do.

### `TemplateColumn` Twig `row` is the `mapRow()` object

In a `TemplateColumn` template, `row` used to be the **array** returned by `mapRow()`. It is now
the **object** passed to `mapRow()` — the projected DTO when `projectPage()` is active, otherwise
the source object. The array is still available, under the new `payload` key.

```twig
{# before #}
<span class="badge">{{ row.status }}</span>
{{ row.fullName }}

{# after #}
<span class="badge">{{ data }}</span>   {# this cell's value #}
{{ payload.fullName }}                  {# any other mapped key #}
{{ row.getFullName() }}                 {# or read the object directly #}
```

`payload` is the exact value `row` used to hold, so a template that only read mapped keys migrates
with a mechanical `row` → `payload` rename. As before, it does not contain cells rendered by other
`TemplateColumn`s, nor the row action metadata.

What changed:

- `row` is the object passed to `mapRow()`; read domain properties from it
- `payload` is the array returned by `mapRow()`; use it for a mapped key other than this cell's
- `data` is unchanged: the resolved value of this cell
- `source` is unchanged: the original hydrated object, same reference as `row` without a projector
- `entity` still works as a deprecated alias of `row`, for `TemplateColumn` templates only. It will
  be removed in a later release
- `item` is no longer provided. It used to be a second alias of `row`; pass it yourself through
  `setTemplate()` parameters if a template of yours needs that name

Collapsible detail rows (`Action::collapsible()`) and edit modals also expose an `entity`
variable. That one is unrelated to `TemplateColumn`, is not an alias, and is not deprecated — leave
those templates alone.

Do not branch on `payload` for authorization or visibility. On the API Platform render route the
rows are posted by the browser, so `payload` is client-controlled input; read `row` or `source`
instead.

### Reserved `TemplateColumn` template parameters now throw

Passing a reserved context key through `setTemplate()` used to be silently dropped, which made a
collision render wrong HTML with no signal. It now fails at configuration time.

```php
use Pentiminax\UX\DataTables\Column\TemplateColumn;

// throws InvalidArgumentException
TemplateColumn::new('status_display')
    ->setTemplate('datatable/columns/status.html.twig', ['row' => $somethingElse]);
```

What changed:

- `setTemplate()` throws `InvalidArgumentException` when `$parameters` uses `row`, `source`,
  `payload`, `data`, `column`, or `entity`
- `payload` is newly reserved; rename such a parameter to something else
- any other key still passes through untouched, `item` included
