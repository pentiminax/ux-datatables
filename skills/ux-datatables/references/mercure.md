# Mercure (real-time updates)

**Opt-in** — present code/attributes do nothing until explicitly enabled. Requires `symfony/mercure-bundle`. The hub URL is resolved automatically at render time.

## Enable

```php
// via configureDataTable()
public function configureDataTable(DataTable $table): DataTable
{
    return $table->mercure(
        topics: [],              // defaults to "/datatables/{pluralized-id}/{id}"
        withCredentials: false,
        debounceMs: null,        // default 500
    );
}

// or via the attribute
#[AsDataTable(User::class, mercure: true)]
#[AsDataTable(User::class, mercure: ['topics' => ['/users'], 'debounceMs' => 300])]
```

When auto-resolution is enabled (`mercure: true`, no explicit topics): the bundle reads the default Symfony Mercure hub URL, reuses explicit API Platform `mercure.topics` when available, otherwise falls back to an item IRI template (`/api/books/{id}`), and falls back to `/datatables/books/{id}` if no API Platform item metadata exists.

`topics` accepts one or many topics — use several when a table must refresh after changes on more than one resource/channel.

## What it does client-side

The Stimulus controller listens to the configured topics (repeating the `topic` query param per topic) and, on each SSE message, dispatches `datatables:mercure:message` and calls `table.ajax.reload(null, false)` (debounced). The connection closes on controller `disconnect()`.

- Only reloads server-side tables — a table configured with static `data` will not auto-refresh from SSE.
- No Mercure config means no SSE subscription (dynamic import skipped).
- `withCredentials` forwards cookies/auth on the SSE request; not mapped automatically from API Platform's `private: true`.

## Publishing updates

If `symfony/mercure` is installed, the bundle aliases `Contracts\MercurePublisherInterface` to `Mercure\MercureUpdatePublisher` (otherwise to `NullMercurePublisher`, a no-op — publishing is always safe to call).

**Mutations publish automatically.** The bundle's delete and inline-edit Ajax controllers call `EntityMutator::delete()` / `setProperty()`, which resolve topics server-side through `Mercure\MercureTopicResolver` (an injected service, not a static call): when the mutation's `dataTableClass` matches a registered `AbstractDataTable` for the same entity, it publishes to that table's *resolved* Mercure config — exactly what the client subscribed to — falling back to the bare entity-class topic otherwise. You never pass topics from the client.

Publish manually with the injected `MercurePublisherInterface`:
```php
$publisher->publish($topics, ['type' => 'custom', 'id' => $id]);
// or, from a DataTable's own resolved config:
$publisher->publishForDataTable($table->getDataTable(), ['type' => 'custom']);
```

## Cross links

- `references/api-platform.md` — `mercure.topics` metadata feeds auto-resolution.
- `docs/src/content/docs/integrations/mercure.mdx` — full reference.
