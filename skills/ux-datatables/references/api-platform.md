# API Platform integration

**Opt-in** — present code/attributes do nothing until explicitly enabled.

Bridges DataTables against an API Platform Hydra collection endpoint. The Stimulus controller translates DataTables query params ↔ API Platform params and Hydra responses ↔ DataTables format.

## Enable

**Attribute** (also enables auto Ajax URL resolution + column auto-detection from API Platform metadata):
```php
#[AsDataTable(Book::class, apiPlatform: true)]
final class BookDataTable extends AbstractDataTable {}
```

**Imperative** (same payload as the attribute, except with `ajax()` until 2.0: only the fluent call sends the API Platform flag and reads that custom Hydra URL through the adapter):
```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table
        ->ajax('/api/books')
        ->serverSide()
        ->apiPlatform(true);
}
```

## What it does

- Maps the global search box to API Platform's search parameter.
- Converts paging/order params to API Platform conventions.
- Reads the Hydra collection response (`hydra:member`, `hydra:totalItems`).
- With the attribute form: resolves the collection URL and auto-detects columns (respecting `serializationGroups`) via `ApiPlatform\ColumnAutoDetector`.

Opt-ins are resolved once after `configureDataTable()`: a fluent value overrides the attribute field by field (null or blank = unset), so `apiPlatform(false)` switches off `apiPlatform: true`. Mercure keeps fluent, then attribute, then auto-resolution. An opt-in with no collection operation throws a `LogicException` at render (data and export routes answer 404), and `data()` + an opt-in throws. `ajax()` + an opt-in (attribute or fluent) sends the API Platform flag: the browser reads the URL through the Hydra adapter; add `->apiPlatform(false)` for plain DataTables JSON.

Define filters/sorting on the API Platform resource side (`#[ApiFilter(...)]`) so the columns you mark `searchable`/`orderable` map to enabled filters. See `docs/src/content/docs/integrations/api-platform.mdx`.

For real-time refresh (Mercure), see `references/mercure.md` — API Platform's `mercure` metadata feeds Mercure topic auto-resolution when both are enabled. The bundle subscribes to the same **absolute** item IRI API Platform publishes (`https://api.example.com/api/books/{id}`), and its own publish path publishes to that same resolved topic; a Mercure 1.0 URL Pattern subscription receives both the concrete item (`/api/books/42`) and the literal template, a group matching the literal placeholder too.
