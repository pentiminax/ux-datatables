# API Platform integration

**Opt-in** — present code/attributes do nothing until explicitly enabled.

Bridges DataTables against an API Platform Hydra collection endpoint. The Stimulus controller translates DataTables query params ↔ API Platform params and Hydra responses ↔ DataTables format.

## Enable

**Attribute** (also enables auto Ajax URL resolution + column auto-detection from API Platform metadata):
```php
#[AsDataTable(Book::class, apiPlatform: true)]
final class BookDataTable extends AbstractDataTable {}
```

**Imperative** (same payload as the attribute; with `ajax()` it reads that custom Hydra URL):
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

Opt-ins: either side opting in counts, and a fluent edit-modal value overrides the attribute (blank = unset). `apiPlatform: true` plus `apiPlatform(false)` stays opted in with a 1.6 deprecation (2.0: fluent wins). An opt-in with no collection operation, or `data()` + `apiPlatform()`, logs a 1.6 deprecation (2.0 throws); `ajax()` + `apiPlatform()` is supported.

Define filters/sorting on the API Platform resource side (`#[ApiFilter(...)]`) so the columns you mark `searchable`/`orderable` map to enabled filters. See `docs/src/content/docs/integrations/api-platform.mdx`.

For real-time refresh (Mercure), see `references/mercure.md` — API Platform's `mercure` metadata feeds Mercure topic auto-resolution when both are enabled. The bundle subscribes to the same **absolute** item IRI API Platform publishes (`https://api.example.com/api/books/{id}`), and its own publish path publishes to that same resolved topic; a Mercure 1.0 URL Pattern subscription receives both the concrete item (`/api/books/42`) and the literal template, a group matching the literal placeholder too.
