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

## Highlighting updated cells

`->highlightUpdates(durationMs: 1200, ignoreColumns: [], idField: 'id')` emphasizes the cells a Mercure refresh changed. Enabling it serializes a `highlight` payload object and adds `DT_RowId` to every row (also fed to the DataTables `rowId` option); with API Platform, whose rows bypass the PHP row mapper, the frontend adapter adds that key from `idField`.

On an SSE message the controller snapshots the displayed values, reloads, then after the draw matches rows by id and compares field by field. Changed cells get the `dt-cell-updated` class; a `datatables:highlight` event carries their nodes.

- Diffing runs on row data, not rendered markup: a redraw regenerates cell HTML everywhere, so markup comparison would light up the whole table.
- Rows are matched by id, so a row that only moved is not reported as updated.
- Only Mercure-triggered refreshes are compared — sorting, searching and paging never highlight.
- `ignoreColumns` is not cosmetic: a relative date or counter column changes on every refresh and would highlight constantly.
- Colors come from `--dt-highlight-color`; `prefers-reduced-motion: reduce` swaps the animation for a static background.

## Cross links

- `references/api-platform.md` — `mercure.topics` metadata feeds auto-resolution.
- `docs/src/content/docs/integrations/mercure.mdx` — full reference.
