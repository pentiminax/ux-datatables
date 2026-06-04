# Gotchas & common mistakes

## Routes not imported → actions/server-side silently fail
Server-side tables, edit/delete actions, and boolean toggles call built-in bundle routes. If nothing happens on Ajax, import them once:
```php
// config/routes/ux_datatables.php
return static function (RoutingConfigurator $routes): void {
    $routes->import('@DataTablesBundle/config/routes.php');
};
```

## Server-side with no data → missing `entityClass`
The Doctrine provider is auto-wired only when `#[AsDataTable(Entity::class)]` carries the entity. No `entityClass` + `serverSide()` and no `createDataProvider()` override → empty table.

## Don't detect Ajax with `isXmlHttpRequest()`
Use the supported flow: `handleRequest()` → `isRequestHandled()` → `getResponse()`. The check is about the DataTables payload, not the browser transport.

## `MoneyColumn` stores cents
If your DB stores amounts in cents, call `->storedAsCents()`, else values are off by 100×. Set `->currency('EUR')` and `->decimals(2)` as needed.

## Client-side is for small datasets (< ~5k rows)
Client-side loads every row into the browser. For large datasets switch to `serverSide()` — otherwise initial load and memory degrade badly.

## `setData()` (entity path) vs `setField()` (query path)
`setData()` is the JSON path the front-end reads; `setField()` is the entity/query path used for server-side filtering & ordering. When displaying a joined relation, set both. Ordering/searching a column whose `field` doesn't map to a real property throws or silently no-ops.

## Mark non-orderable/non-searchable columns
Computed or template columns have no DB counterpart. Set `->setOrderable(false)->setSearchable(false)` (or `->disableGlobalSearch()`) to avoid invalid query attempts.

## Actions have no `linkToRoute()`
`Action` exposes only `linkToUrl(string|callable)`. Build the route URL inside the callable, or use a `UrlColumn` (which does have `linkToRoute()`).

## API Platform / Mercure are opt-in
Neither activates implicitly. Set `apiPlatform: true` / call `apiPlatform()`, and `mercure: true` / call `mercure()`. Define API Platform filters on the resource so searchable/orderable columns map to enabled filters.

## `permission()` removes, never hides client-side
Static `permission()` on actions/columns is evaluated server-side before serialization; ungranted items are dropped and the attribute name is never sent to the browser. Don't rely on it for purely visual toggling — use `displayIf()` (actions) or `setVisible()` (columns) for that.

## `ButtonType::COLUMN_VISIBILITY`
The enum case is `COLUMN_VISIBILITY` (serialized value `'colvis'`), not `COL_VIS`.
