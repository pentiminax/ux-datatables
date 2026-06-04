# API Platform & Mercure integration

Both integrations are **opt-in** — present code/attributes do nothing until explicitly enabled.

## API Platform

Bridges DataTables against an API Platform Hydra collection endpoint. The Stimulus controller translates DataTables query params ↔ API Platform params and Hydra responses ↔ DataTables format.

### Enable

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

What it does:
- Maps the global search box to API Platform's search parameter.
- Converts paging/order params to API Platform conventions.
- Reads the Hydra collection response (`hydra:member`, `hydra:totalItems`).
- With the attribute form: resolves the collection URL and auto-detects columns (respecting `serializationGroups`).

Define filters/sorting on the API Platform resource side (`#[ApiFilter(...)]`) so the columns you mark `searchable`/`orderable` map to enabled filters. See `docs/src/content/docs/integrations/api-platform.mdx`.

## Mercure (real-time updates)

Requires `symfony/mercure-bundle`. The hub URL is resolved automatically at render time.

```php
// via the DataTable builder
$table->mercure(
    topics: [],              // defaults to "/datatables/{pluralized-id}/{id}"
    withCredentials: false,
    debounceMs: null,        // default 500
);

// or via the attribute
#[AsDataTable(User::class, mercure: true)]
#[AsDataTable(User::class, mercure: ['topics' => ['/users'], 'debounceMs' => 300])]
```

The Stimulus controller listens to the configured topics and reloads the table (debounced) on each SSE message, dispatching `datatables:mercure:message` for custom handling. See `docs/src/content/docs/integrations/mercure.mdx`.
