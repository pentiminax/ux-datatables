# Columns

All columns live in `src/Column/`. Every type is created with the static factory `Type::new($name, $title = '')` and configured fluently. If `$title` is empty it defaults to `$name`.

## Column types

| Type | Factory | Type-specific methods |
|------|---------|-----------------------|
| `TextColumn` | `TextColumn::new('name', 'Name')` | `utf8()`, `html()` |
| `NumberColumn` | `NumberColumn::new('qty')` | `formatted()`, `html()` |
| `DateColumn` | `DateColumn::new('createdAt')` | `setFormat('d/m/Y H:i')` |
| `MoneyColumn` | `MoneyColumn::new('price')` | `currency('EUR')`, `storedAsCents(bool=true)`, `decimals(int)`, `showCurrencySign(bool=true)` |
| `BooleanColumn` | `BooleanColumn::new('active')` | `renderAsSwitch(bool $defaultState=false)`, `setToggleAjax(string $idField='id', string $method='PATCH')`, `setEntityClass(...)` |
| `ChoiceColumn` | `ChoiceColumn::new('status')` | `setChoices(array\|string)`, `renderAsBadges(array\|bool $selector=[], string $defaultVariant='secondary')` |
| `EmailColumn` | `EmailColumn::new('email')` | `obfuscate(bool=true)`, `mask(bool=true)`, `setDisplayValue(string)`, `renderAsText(bool=true)` |
| `ImageColumn` | `ImageColumn::new('photo')` | `setImageWidth(int)`, `setImageHeight(int)`, `setAlt(string)`, `setPlaceholder(string)`, `rounded()`, `lazy()`, `clickable()` |
| `UrlColumn` | `UrlColumn::new('website')` | `linkToUrl(string\|callable)`, `linkToRoute(string $route, array\|callable\|null $params=null)`, `openInNewTab()`, `showExternalIcon(bool=true)`, `setDisplayValue(string)`, `setDefaultProtocol(string)`, `allowedProtocols(array)` |
| `TemplateColumn` | `TemplateColumn::new('preview')` | `setTemplate(string $template, array $parameters=[])` — server-side Twig rendering |
| `ActionColumn` | *(auto-generated from `configureActions()` — do not build manually)* | see `references/actions.md` |

> `UrlColumn` supports `linkToRoute()`; `Action` (row actions) does **not** — it only has `linkToUrl()`.

## Shared methods (`AbstractColumn`)

Available on every column:

```php
TextColumn::new('email', 'Email')
    ->setWidth('200px')            // any CSS unit
    ->setClassName('text-truncate')
    ->setCellType('th')            // 'td' (default) or 'th'
    ->setVisible(true)
    ->setOrderable(false)          // disable sorting on this column
    ->setSearchable(false)         // disable per-column search
    ->disableGlobalSearch()        // keep searchable but exclude from the global box
    ->setOrderExpression('invoiceCount')  // raw ORDER BY (DQL/SELECT alias) for a computed column — see server-side.md
    ->setData('user.email')        // data source path (client-side rows)
    ->setField('user.email')       // entity property path (server-side query/sort)
    ->setDefaultContent('—')       // fallback when value is null/missing
    ->setExportable(false)         // exclude from export (adds 'not-exportable' class)
    ->hideWhenUpdating()           // hide field in the edit modal
    ->permission('ROLE_ADMIN')     // hide column unless granted (evaluated server-side, never sent to client)
    ->setCustomOption('key', 'value');
```

Notes:
- `setField()` vs `setData()`: `field` is the entity/query path used for server-side filtering & ordering; `data` is the JSON path the front-end reads. Set `field` when the displayed property differs from the queried one (e.g. joined relations).
- `permission()` on a column is evaluated once before serialization — the attribute name is never exposed to the browser.
- Define custom JavaScript render callbacks with the Stimulus `datatables:pre-connect` event (see `references/server-side.md`).
- `setOrderExpression()` is for computed columns sorted on a subquery: pair it with an `addSelect(... AS HIDDEN <alias>)` in `customizeQueryBuilder()` — see `references/server-side.md`.
