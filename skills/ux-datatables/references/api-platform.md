# API Platform integration

**Opt-in** — present code/attributes do nothing until explicitly enabled.

Bridges DataTables against an API Platform Hydra collection endpoint. The Stimulus controller translates DataTables query params ↔ API Platform params and Hydra responses ↔ DataTables format.

## Enable

**Attribute** (also enables auto Ajax URL resolution + column auto-detection from API Platform metadata):
```php
#[AsDataTable(Book::class, apiPlatform: true)]
final class BookDataTable extends AbstractDataTable {}
```

**Imperative** (client-side adapter only):
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

Define filters/sorting on the API Platform resource side (`#[ApiFilter(...)]`) so the columns you mark `searchable`/`orderable` map to enabled filters. See `docs/src/content/docs/integrations/api-platform.mdx`.

For real-time refresh (Mercure), see `references/mercure.md` — API Platform's `mercure` metadata feeds Mercure topic auto-resolution when both are enabled.
