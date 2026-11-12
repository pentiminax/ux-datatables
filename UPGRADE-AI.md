# Upgrade Guide for AI Agents

This guide is for AI coding agents. If you are a human, read [UPGRADE.md](UPGRADE.md) instead.

Each section below is a self-contained prompt for one release. Apply the sections between the
current version (excluded) and the target version (included), newest last. Required changes keep
the application working on the next major version. Optional changes are safe to skip.

<upgrade-prompt version="1.6">

Context: ux-datatables 1.6 is backward compatible. It adds a bare fluent method for every
user-facing `setX()` on columns, actions, bulk actions, table options and the table model, and
deprecates the `setX()` spelling. 2.0 removes the `setX()` methods. After this change, running the
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
- `DataTable`: `setAttributes()` → `attributes()`, `setPermission()` → `permission()`, `setHighlightConfig()` → `highlightConfig()`
- `AbstractDataTable`: `setData()` → `data()`

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
   browser console logs "The style framework was autodetected". Call `styleFramework()` on the
   table, or install a style kit.

   ```php
   $table->styleFramework(StyleFramework::Bootstrap5);
   ```

## Optional changes

1. `#[DataTableColumn(...)]` attribute options need no change: they resolve to the bare methods and
   raise no deprecation.
2. Getters (`getX()`, `isX()`) did not change.
3. Methods marked `@internal`, the `setPrepared*()` mutators and `setDataTableInfrastructure()`
   stay as they are: they are removed with the per-render view object in 2.0 and are not meant to
   be called by applications.

</upgrade-prompt>
