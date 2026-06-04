# Server-side processing

Use server-side when the dataset is large or filtering/sorting must happen in the database. The browser sends every paging/search/order change to the backend.

## Enable

```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table->serverSide()->processing();
}
```

With `#[AsDataTable(Entity::class)]` set, the `DoctrineDataProvider` is auto-wired — no manual provider needed.

## Import the bundle routes (required)

The rendered table calls the built-in `ux_datatables_ajax_data` route (it sends an opaque table token, never the PHP class name). Import the routes **once**:

```php
// config/routes/ux_datatables.php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@DataTablesBundle/config/routes.php');
};
```

Without this, server-side tables, row actions (edit/delete), and boolean toggles silently fail.

## Controller flow

Two options:

**A. Auto Ajax (recommended)** — just render; the bundle resolves the tagged service and handles the Ajax route internally:
```php
#[Route('/products', name: 'app_products')]
public function index(ProductsDataTable $table): Response
{
    return $this->render('product/index.html.twig', ['table' => $table]);
}
```

**B. Same-route handling** — when posting back to the same route or using a custom Ajax URL:
```php
$table->handleRequest($request);
if ($table->isRequestHandled()) {
    return $table->getResponse();
}
return $this->render(...);
```
Do **not** gate on `$request->isXmlHttpRequest()`. The supported detection flow is exactly: `handleRequest()` → `isRequestHandled()` → `getResponse()`.

## Custom Ajax URL

For a plain `DataTable` instance or a non-`AbstractDataTable` endpoint:

```php
$table->ajax('/api/products', dataSrc: null, type: 'GET')->serverSide();
// or with extra request data merged into every call:
$table->ajaxRequestData('/api/products', ['tenant' => 5], 'GET');
```

## Forward page query parameters

When using Auto Ajax, the browser sends its request to the bundle endpoint, not the page URL — so contextual query parameters present on the page (`?q=...&pending=...`) never reach the server. `forwardQueryParameters()` captures the named params **at render time** and forwards them on every Ajax call, so you can read them in `customizeQueryBuilder()` without writing a dedicated relay controller:

```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table
        ->serverSide()
        ->forwardQueryParameters(['q', 'pending']);
}

protected function customizeQueryBuilder(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
{
    $request = $this->getHttpRequest();

    if (null !== $pending = $request?->query->get('pending')) {
        $qb->andWhere('e.pending = :pending')->setParameter('pending', $pending);
    }

    return $qb;
}
```

Values are a **snapshot** taken when the page renders (sent unchanged on every paging/search/sort reload); only params present in the request are forwarded. Works with Auto Ajax and manual `ajax()` / `ajaxRequestData()` alike. Not applied in API Platform mode.

## Frontend hooks (Stimulus)

The controller is registered as `@pentiminax/ux-datatables/datatable` and dispatches events with the `datatables` prefix. Attach a custom controller via `render_datatable(table, {'data-controller': 'my-table'})` and listen:

| Event | When | `event.detail` |
|-------|------|----------------|
| `datatables:pre-connect` | before init | `{ config }` — mutate columns/options, set `render` callbacks |
| `datatables:connect` | after init | `{ table }` — the DataTables API instance |
| `datatables:mercure:message` | on SSE message | `{ data, event }` |

```js
// assets/controllers/my-table_controller.js
export default class extends Controller {
  connect() {
    this.element.addEventListener('datatables:pre-connect', e => {
      e.detail.config.columns[0].render = (d, t, r) => `<a href="#">${d}</a>`
    })
    this.element.addEventListener('datatables:connect', e => {
      e.detail.table.on('draw', () => console.log('redrawn'))
    })
  }
}
```
