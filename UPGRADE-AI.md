# Upgrade Guide for AI Agents

This guide is for AI coding agents. If you are a human, read [UPGRADE.md](UPGRADE.md) instead.

Each section below is a self-contained prompt for one release. Apply the sections between the
current version (excluded) and the target version (included), newest last. Required changes keep
the application working on the next major version. Optional changes are safe to skip.

<upgrade-prompt version="1.6">

Context: ux-datatables 1.6 is backward compatible except for six behavior changes: a subclass
override of a deprecated `setX()` is no longer reached (required change 12), server-side Doctrine
tables fetch-join the to-one relations their displayed columns read (optional change 4),
`DataSourceFeature` gains two cases (required change 13), the fluent API Platform flag is withdrawn
from a table with `data()` and a blank edit modal template or adapter counts as unset (required
change 9), and the Ajax data and export endpoints answer 404 for an API Platform table without a
collection operation (required change 7). It adds a bare fluent method for every user-facing
`setX()` on columns, actions, bulk actions, table options and the table model, and deprecates the
`setX()` spelling. 2.0 removes the `setX()` methods. After this change, running the
test suite and the application must log no `pentiminax/ux-datatables` deprecation.

## Required changes

1. Rename every call of a deprecated `setX()` method to its bare `x()` method. The arguments and the
   return value are identical, so this is a pure search and replace on method names. Only rename
   calls whose receiver is a ux-datatables object (a column, an `Action`, `Actions`, `BulkAction`,
   `BulkActions`, a `DataTable` or its options). Leave unrelated `setX()` calls alone, for example
   form, request and entity setters.

- `AbstractColumn`: `setClassName()` → `className()`, `setCellType()` → `cellType()`, `setName()` → `name()`, `setOrderable()` → `orderable()`, `setSearchable()` → `searchable()`, `setSearchNormalization()` → `searchNormalization()`, `setColumnControl()` → `columnControl()`, `setType()` → `type()`, `setWidth()` → `width()`, `setResponsivePriority()` → `responsivePriority()`, `setTitle()` → `title()`, `setVisible()` → `visible()`, `setData()` → `data()`, `setDefaultContent()` → `defaultContent()`, `setExportable()` → `exportable()`, `setField()` → `field()`, `setOrderExpression()` → `orderExpression()`, `setSearchField()` → `searchField()`, `setSearchPredicate()` → `searchPredicate()`, `setCustomOption()` → `customOption()`, `setPermission()` → `permission()`
- `ImageColumn`: `setImageWidth()` → `imageWidth()`, `setImageHeight()` → `imageHeight()`, `setAlt()` → `alt()`, `setPlaceholder()` → `placeholder()`
- `ChoiceColumn`: `setChoices()` → `choices()`
- `UrlColumn`: `setDisplayValue()` → `displayValue()`, `setDefaultProtocol()` → `defaultProtocol()`
- `EmailColumn`: `setDisplayValue()` → `displayValue()`
- `DateColumn`: `setFormat()` → `format()`
- `BooleanColumn`: `setToggleAjax()` → `toggleAjax()`, `setEntityClass()` → `entityClass()`
- `TemplateColumn`: `setTemplate()` → `template()`
- `Action`: `setClassName()` → `className()`, `setEntityClass()` → `entityClass()`, `setIdField()` → `idField()`, `setPermission()` → `permission()`
- `BulkAction`: `setClassName()` → `className()`, `setPermission()` → `permission()`
- `Actions`: `setColumnLabel()` → `columnLabel()`, `setColumnClassName()` → `columnClassName()`, `setColumnControl()` → `columnControl()`
- `BulkActions`: `setIdField()` → `idField()`
- `DataTableOptions`: `setLanguage()` → `language()`, `setSearch()` → `search()`
- `AbstractDataTable`: `setData()` → `rows()`; `DataTable`: `setAttributes()` → `attributes()`, `setPermission()` → `permission()`, `setHighlightConfig()` → `highlightConfig()`
- `AbstractDataTable`: `setData()` → `rows()`

   Before:

   ```php
   TextColumn::new('name')->setTitle('Name')->setOrderable(false)->setPermission('ROLE_ADMIN');
   ```

   After:

   ```php
   TextColumn::new('name')->title('Name')->orderable(false)->permission('ROLE_ADMIN');
   ```

2. Replace `Action::csrfTokenId()` with `Action::csrfToken()`. The arguments are identical.

   ```php
   Action::delete()->csrfTokenId('delete_book'); // before
   Action::delete()->csrfToken('delete_book');   // after
   ```

3. Replace `DataTable::setExtensions(DataTableExtensions $extensions)`, which replaced the whole
   extension collection, with `extensions([...])`, which adds to it. Declare each extension
   explicitly.

   ```php
   $table->setExtensions(new DataTableExtensions([new ResponsiveExtension()])); // before
   $table->extensions([new ResponsiveExtension()]);                                // after
   ```

4. When the application builds a bundle service by hand (`new ColumnResolver()`, `new
   DataTableRuntimeFactory()`, `new DetailRowService(...)`, and so on) without an authorization
   checker, pass `Pentiminax\UX\DataTables\Security\AuthorizationChecker` explicitly. Services that
   come from the container already receive one: change nothing there.

   ```php
   new ColumnResolver();                                                    // before
   new ColumnResolver(permissionChecker: new AuthorizationChecker($security)); // after
   ```

5. Make every custom column extend a bundled column or `AbstractColumn`. Search for classes that
   `implements ColumnInterface` and do not extend `AbstractColumn`, and for classes that implement
   `SearchableColumnInterface` or `NormalizedSearchColumnInterface`: extend `AbstractColumn`
   instead, set the type in the constructor, and drop the search interfaces (their members already
   exist on `AbstractColumn`). Replace `setField()` and `setVisible()` calls with `field()` and
   `visible()`. Type hints of `ColumnInterface` in search strategies and options providers stay.
6. Set the style framework explicitly when the page relied on stylesheet autodetection and the
   browser console logs "The style framework was autodetected". Set `data_tables.style_framework`
   (`dt` or `bs5`) or call `styleFramework()` on the table.

   ```php
   $table->styleFramework(StyleFramework::Bootstrap5);
   ```

7. For a table opted into API Platform (`#[AsDataTable(apiPlatform: true)]` or `apiPlatform()`)
   whose entity exposes no collection operation, the Ajax data and export endpoints now answer 404
   and no export URL is rendered, as render already read no rows. Expose a collection operation on
   the entity, or drop `apiPlatform`.
8. Fix every custom data provider (a class implementing `DataProviderInterface`) and every table
   that logs a `pentiminax/ux-datatables` deprecation about the row scope, the export or feature
   awareness. The built-in `DoctrineDataProvider` needs no change.

   - Provider implements `ScopedIdentifierProviderInterface` and/or
     `IdentifierCollectingDataProviderInterface`: implement `IdentifierDataProviderInterface`
     instead (`collectIdentifiers()` and `filterIdentifiersInScope()`, same signatures), and return
     `true` from `supports()` for `DataSourceFeature::RowScope` and
     `DataSourceFeature::IdentifierCollection`.
   - Provider does not implement `FeatureAwareDataProviderInterface`: implement it and answer
     `supports()` for each `DataSourceFeature` case, with a `default` arm or all six cases when
     using `match`.
   - Provider does not implement `StreamingDataProviderInterface`: implement `iterateRows()`.
   - Table logs "skips the row scope check" and its provider cannot check the scope (API Platform
     table, array or custom provider on a table with row actions, toggles, edit, detail or bulk
     actions): when the scope is enforced elsewhere, add `unscopedMutations()` to the table.
     Otherwise give the provider the identifier interface above.

   ```php
   public function configureDataTable(DataTable $table): DataTable
   {
       return $table->unscopedMutations();
   }
   ```

9. Fix every table that logs a deprecation about an API Platform opt-in, and the two behavior
   changes beside it. Opted into API Platform with no collection operation (or without API Platform
   installed): expose the operation or drop the opt-in. `data()` combined with `apiPlatform()` or the
   attribute opt-in: keep one of them, as 2.0 throws (a fluent flag is already withdrawn). `ajax()` combined with
   the fluent `apiPlatform()` is supported and needs no change. `#[AsDataTable(apiPlatform: true)]`
   combined with `ajax()` and no fluent call: add `->apiPlatform(false)` to keep plain DataTables
   JSON, or `->apiPlatform()` to send the flag now, as 2.0 sends it. `#[AsDataTable(apiPlatform:
   true)]` combined with `apiPlatform(false)` and no `ajax()`: remove one, as 2.0 lets the fluent `false` win.
   Replace a blank `editModalTemplate('')` or `editModalAdapter('')` with a real value or remove it:
   a blank value now counts as unset on the page, as it already did on the edit route.

10. Replace the deprecated rendering entry points and flags.

    - `$table->getDataTable()` or `$table->prepareForRendering()` on an `AbstractDataTable`: read
      with `$table->getConfiguredDataTable()`, render with `render_datatable()`.
    - `$publisher->publishForDataTable($table->getDataTable(), $data)`: pass the table itself,
      `$publisher->publishForDataTable($table, $data)`.
    - `->apiPlatformServerSide()` on a `DataTable`: delete the call, the bundle sets the flag.
    - Code reading bundle keys (`apiPlatform`, `mercure`, `bulkActions`) through `getOption()` or
      `getOptions()`: 2.0 returns DataTables.net options only, no notice is raised for it.

11. Fix every entity table that logs the "never called serverSide()" deprecation. An entity table
    (`#[AsDataTable(entityClass: ...)]`) with no `serverSide()` call, no `ajax()` and no `data()`
    reads and embeds all its rows at render time. For a table that can be large, add
    `->serverSide()` to its `configureDataTable()`. For a small lookup table whose rows should stay
    in the page, add `->serverSide(false)`: it silences the deprecation and keeps today's behavior
    in 2.0. Do not add either call to tables that already use `ajax()`, `data()` or `apiPlatform()`.
    A table switched to `serverSide()` is served through the bundle's Ajax data route: confirm the
    application's security configuration protects it.
12. Rename every override of a deprecated `setX()` in a subclass of a bundle column, `Action`,
    `BulkAction`, `DataTable` or `AbstractDataTable` to the bare `x()` method, with the same
    signature: the bundle now calls `x()`, so an override of `setX()` is no longer reached. A
    subclass that already declares a method named like a new bare method (`rows()`, `data()`,
    `name()`, `type()`, `title()`, `format()`, `template()`, `choices()`, ...) must rename it or
    match the new signature.

    ```php
    public function setTitle(string $title): static { ... } // before
    public function title(string $title): static { ... }    // after
    ```

13. Handle the new `DataSourceFeature` cases `RowScope` and `IdentifierCollection` in application
    code that iterates `DataSourceFeature::cases()` or matches on it exhaustively: add both cases or
    a `default` arm.
14. Add a bare `foo()` method to every custom column that accepts an attribute option only through
    its own `setFoo()` method: such an option logs a deprecation, and 2.0 calls only `foo()`.
15. Remove deep imports of `dist/functions/filterFeature.js`, `filterLayout.js`,
    `bulkActionsFeature.js` and `bulkActionsLayout.js` (the controller wires both bars itself), and
    replace `confirmBulkAction()` from `dist/bulk/confirmModal.js` with `confirmAction()`. These
    1.2 deprecations now log a browser console warning.

## Optional changes

1. `#[DataTableColumn(...)]` attribute options need no change: they resolve to the bare methods and
   raise no deprecation.
2. Getters (`getX()`, `isX()`) did not change.
3. Methods marked `@internal`, the `setPrepared*()` mutators and `setDataTableInfrastructure()`
   stay as they are: they are removed with the per-render view object in 2.0 and are not meant to
   be called by applications.
4. Server-side tables backed by the Doctrine provider now fetch-join the to-one associations their
   displayed columns read through a dot path (`customer.name`). Rows, counts and the JSON payload
   are identical. Only review code that relied on the related entity being a lazy proxy (an
   `instanceof Proxy` check, or a `postLoad` listener on it firing later).

</upgrade-prompt>

<upgrade-prompt version="1.7">

Context: ux-datatables 1.7 is backward compatible. It deprecates what 2.0 removes: the runtime
accessors of `AbstractDataTable` and the `--prune` option of `ux-datatables:install`. 2.0 also
requires PHP 8.3, Symfony 7.4 or 8.x, API Platform 4 or later and `symfony/mercure` 0.8 or later
(announcement only, no deprecation). After this change, running the test suite and the application
must log no `pentiminax/ux-datatables` deprecation.

## Required changes

1. Replace every custom endpoint that chains `handleRequest()`, `isRequestHandled()` and
   `getResponse()` on an `AbstractDataTable` with `DataTableResponder::respond()`. Inject
   `Pentiminax\UX\DataTables\Runtime\DataTableResponder` in the controller. It returns the JSON
   response, or `null` when the request carries no DataTables payload.

   ```php
   $table->handleRequest($request);                  // before
   if ($table->isRequestHandled()) {
       return $table->getResponse();
   }

   if ($response = $responder->respond($table, $request)) { // after
       return $response;
   }
   ```

2. Replace `getRequest()` calls inside a table with `getCurrentRequest()`, or with the `$request`
   argument of `customizeQueryBuilder()`. Remove calls to `fetchData()` and `getDataProvider()` made
   from application code: they have no replacement, 2.0 moves them to the bundle runtime. Call the
   data provider the application built itself.
3. Remove every subclass override of `handleRequest()`, `isRequestHandled()`, `getResponse()`,
   `getRequest()` or `fetchData()`: 1.x still calls it and logs a deprecation, 2.0 does not. Use
   `DataTableResponder::respond()` for the first three, and move the logic of the other two to
   `customizeQueryBuilder()` (or read the request with `getCurrentRequest()`), which receives the `DataTableRequest` as its `$request` argument.
4. Remove `--prune` from every `ux-datatables:install` invocation (scripts, Composer hooks, CI):
   2.0 removes the option.

## Optional changes

1. Check the platform before upgrading to 2.0: PHP 8.3, Symfony 7.4 or 8.x, API Platform 4 or later
   when used, `symfony/mercure` 0.8 or later when used.

</upgrade-prompt>

<upgrade-prompt version="2.0">

Context: ux-datatables 2.0 removes what 1.x deprecated. This section covers rendering (rendering no
longer writes to the table, and the option bag holds DataTables.net options only) and columns
(`AbstractColumn` is the only column implementation, and every column `setX()` method is gone) and
data providers (`DataProviderInterface` is the only provider contract). After this
change, the application and its test suite must run with no call to a removed method.

## Required changes

1. Replace `$table->getDataTable()` and `$table->prepareForRendering()` on an `AbstractDataTable`:
   read configuration with `$table->getConfiguredDataTable()`, render with `render_datatable()` in
   Twig. Remove subclass overrides of `getDataTable()` or `prepareForRendering()`: 2.0 never calls
   them. Move their logic to `configureDataTable()`.
2. Replace `$publisher->publishForDataTable($table->getDataTable(), $data)` with
   `$publisher->publishForDataTable($table, $data)`, passing the `AbstractDataTable`.
3. Delete every `->apiPlatformServerSide()` call on a `DataTable`.
4. Find code reading a bundle key through `DataTable::getOption()` or `getOptions()` (`apiPlatform`,
   `apiPlatformServerSide`, `pollInterval`, `urlState`, `styleFramework`, `exportUrl`,
   `showHeaderResetButton`, `mercure`, `highlight`, `bulkActions`, `rowActionUrls`, `filters`,
   `filterLabels`, `emptyState`, `actionLabels`, `summaryLabels`, `forwardedQuery`, `recordUrl`).
   2.0 returns `null` or omits the key. Read the configuration from its own getter
   (`getMercureConfig()`, `getHighlightConfig()`, `getBulkActions()`, `getFilters()`,
   `getEmptyState()`), or, in tests, render the table and read the payload with `DataTableView`.
5. Move every change to `ajax()`, `data()`, `serverSide()`, `apiPlatform()`, `mercure()`,
   `editModalTemplate()` or `editModalAdapter()` made through `getConfiguredDataTable()` after the
   table was configured into `configureDataTable()`: 2.0 resolves those opt-ins once. Calls to
   `rows()` from a controller need no change.
6. If the application constructs `DataTablesExtension` itself, pass only the Stimulus helper, the
   profiler, the authorization checker and the controller name.
7. Search the payload for a key set both as an option and as an extension: 2.0 throws a
   `LogicException` at render for it. Keep one of the two.
8. Stop calling `RenderingPreparer`, `FilterEntityOptionsResolver` and `SearchListOptionsResolver`
   directly: they are `@internal` and their signatures changed. `RenderingPreparer::prepare()` is
   removed. `prepareBeforeDataHydration()`, `prepareAfterDataHydration()` and
   `resolveMercureConfig()` lose the `?AsDataTable` parameter, and `prepareAfterDataHydration()`
   returns a `PreparedDataTable`. `FilterEntityOptionsResolver::prepare()` takes the `Filters`.
   `SearchListOptionsResolver::prepare()` is removed.
9. Purge HTTP and page caches that hold pages rendered before 1.5.1, and make sure the bundle's
   routes are imported: the browser refuses a row action (delete, toggle, detail, edit) whose URL
   is missing from the payload's `rowActionUrls` instead of falling back to a default path.
10. Rename every remaining `setX()` call on a column to its bare `x()` method, with the same
    arguments (see 1.6 required change 1 for the full list per column class): `setTitle()` →
    `title()`, `setField()` → `field()`, `setVisible()` → `visible()`, `setImageWidth()` →
    `imageWidth()`, `setChoices()` → `choices()`, `setDisplayValue()` → `displayValue()`,
    `setDefaultProtocol()` → `defaultProtocol()`, `setFormat()` → `format()`, `setToggleAjax()` →
    `toggleAjax()`, `setEntityClass()` → `entityClass()`, `setTemplate()` → `template()`, and so on.
    Replace `ColumnResolver::removeDeniedColumnValues($row, $columns)` with
    `removePaths($row, $resolver->deniedColumnPaths($columns))`.
11. Make every custom column extend a bundled column or `AbstractColumn`: search for classes that
    `implements ColumnInterface`, `SearchableColumnInterface` or `NormalizedSearchColumnInterface`
    and rewrite them as subclasses of `AbstractColumn` (set the type through `createWithType()` or the
    constructor, delete the members `AbstractColumn` already provides). Delete every `use` of
    `SearchableColumnInterface` and `NormalizedSearchColumnInterface`. Keep `ColumnInterface` type
    hints in search strategies, predicate builders and `SearchListOptionsProviderInterface`
    implementations. Type hints on column lists passed to `DataTable::add()` or returned from
    `configureColumns()` must be `AbstractColumn`.
12. Replace the `#[Column(...)]` attribute (`Pentiminax\UX\DataTables\Attribute\Column`) with
    `#[DataTableColumn(...)]`: keep `type`, `name` and `position` as named parameters and move every
    other named parameter into `options: ['title' => ..., 'orderable' => ...]`.
13. Add a bare `foo()` method to every custom column or filter that accepts an attribute option
    only through its own `setFoo()` method: 2.0 calls only `foo()` and throws "is not supported
    by" otherwise.
14. Rename the remaining model `setX()` calls to their bare method, with the same arguments:
    `Action`: `setClassName()` → `className()`, `setEntityClass()` → `entityClass()`,
    `setIdField()` → `idField()`, `setPermission()` → `permission()`; `BulkAction`: `setClassName()`
    → `className()`, `setPermission()` → `permission()`; `Actions`: `setColumnLabel()` →
    `columnLabel()`, `setColumnClassName()` → `columnClassName()`, `setColumnControl()` →
    `columnControl()`; `BulkActions`: `setIdField()` → `idField()`; `DataTableOptions`:
    `setLanguage()` → `language()`, `setSearch()` → `search()`; `DataTable`: `setAttributes()` →
    `attributes()`, `setPermission()` → `permission()`, `setHighlightConfig()` →
    `highlightConfig()`.
15. Replace `Action::csrfTokenId($id)` with `Action::csrfToken($id)` (same arguments) and
    `DataTable::setExtensions($extensions)` with `extensions([...])`.
16. Delete application calls to `EntityMutator::delete()` and `setProperty()`,
    `DetailRowService::handleView()` and `EditFormService::handleView()` / `handleSubmit()`: the
    bundle's Ajax controllers are the supported entry points, and the services now take an
    `AuthorizedRow` through `deleteRow()`, `toggleRow()`, `render()`, `renderForm()` and
    `submitForm()`.
17. Remove the fourth positional argument (`Filters`) of `new ArrayDataProvider(...)`, and the second
    argument of `FilterTypeMapper::mapType()`. If an intent factory was passed fifth, pass it fourth
    or as `intentFactory:`.
18. Fix every custom data provider (a class implementing `DataProviderInterface`). The built-in
    providers need no change.
    - Add `supports(DataSourceFeature $feature): bool` and `iterateRows(DataTableRequest $request):
      iterable` to the class: both are now abstract on `DataProviderInterface`. Answer every
      `DataSourceFeature` case in `supports()`, with `false` for features the provider does not
      apply; an `\UnhandledMatchError` counts as unsupported.
    - Remove `FeatureAwareDataProviderInterface` and `StreamingDataProviderInterface` from the
      `implements` list and their `use` statements: they no longer exist.
    - Replace `ScopedIdentifierProviderInterface` and `IdentifierCollectingDataProviderInterface`
      with `IdentifierDataProviderInterface` (`collectIdentifiers()` and
      `filterIdentifiersInScope()`, same signatures), and return `true` from `supports()` for
      `DataSourceFeature::RowScope` and `DataSourceFeature::IdentifierCollection`: without that
      answer the bundle ignores the interface. Answering `false` (or `default => false`) for
      `RowScope` means the provider is treated as unscoped: the row scope check is disabled for
      row actions and bulk identifiers.

</upgrade-prompt>
