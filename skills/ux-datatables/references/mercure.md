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

The Stimulus controller listens to the configured topics and, on each SSE message, dispatches `datatables:mercure:message` and calls `table.ajax.reload(null, false)` (debounced). The connection closes on controller `disconnect()`.

The subscription dialect follows the hub's protocol version, read server-side from `HubInterface::getProtocolVersion()` (symfony/mercure 0.8+) and serialized as `protocolVersion` only when it is not `0.x`. Nothing to configure in the bundle — the dialect follows the hub. A 1.0 hub that is sent `topic=` creates no subscription at all: the connection opens and nothing ever arrives, so check the protocol version before the topics when live updates stop after a hub upgrade.

- 0.x hub — one `topic=` query parameter per topic, with URI Template selectors.
- 1.0 hub — `match_urlpattern=` with `/books/{id}` rewritten to `/books/:p0` for a templated topic (the group name is generated: RFC 6570 variable names are not all valid URL Pattern group names — `:book.id` is not a group, `:1` throws — and a repeated variable would collide), `match=<topic>` for a plain one, and `match=` on the literal value for a topic using an RFC 6570 operator (`{?page}`), which has no URL Pattern equivalent. A group also matches the literal placeholder, so API Platform's `/api/books/{id}` publications keep matching.
- No reported version (older symfony/mercure) — legacy `topic=` parameters.

Publishing is protocol-agnostic: Mercure 1.0 kept the `topic=` form fields, so the publish path needs no change. Hub-side, a 1.0 hub needs `protocol_version: '1.0'` plus `jwt.claims` (RFC 9068 requires `iss`/`sub`/`client_id`), and `protocol_version_compatibility 8` keeps 0.x clients working while you migrate.

- Only reloads server-side tables — a table configured with static `data` will not auto-refresh from SSE.
- No Mercure config means no SSE subscription (dynamic import skipped).
- `withCredentials` forwards cookies/auth on the SSE request; not mapped automatically from API Platform's `private: true`. On a 1.0 hub the cookie is `__Secure-mercure_access_token`, so private topics also need an HTTPS hub URL.

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
