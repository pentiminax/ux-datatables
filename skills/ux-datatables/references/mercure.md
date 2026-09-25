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

When auto-resolution is enabled (`mercure: true`, no explicit topics): the bundle reads the default Symfony Mercure hub URL, reuses explicit API Platform `mercure.topics` when available, otherwise builds the item IRI template **absolutely** from the routing request context (`https://api.example.com/api/books/{id}`), and falls back to the bundle's own `/datatables/books/{id}` if no API Platform item metadata exists. That fallback stays **relative** on purpose — only the bundle publishes to it, through this same resolver, so a context-free topic matches wherever each side runs. Only the API Platform item topic is absolutized: without a router the path stays relative, and with one the context is the very one API Platform generates its IRIs from, which in a console command or Messenger consumer is `router.request_context` (default `http://localhost` — set `host`/`scheme` there if anything outside an HTTP request publishes).

`topics` accepts one or many topics — use several when a table must refresh after changes on more than one resource/channel.

**The auto-resolved topic is the IRI API Platform publishes.** API Platform publishes the item IRI computed at `UrlGeneratorInterface::ABS_URL` — that is what `mercure: true`, an omitted `topics` key, and `['topics' => ['@=iri(object)']]` all resolve to (`PublishMercureUpdatesListener::publishUpdate()`). The bundle subscribes to that same absolute URL: `Mercure\MercureTopicUrlResolver` reads the routing request context (the one the URL generator uses) and builds scheme, host, port and base path exactly like the generator, so the subscription matches even when the hub lives on another host or subdomain. Before, the bundle subscribed to the *relative* item route path, and what that cost depended on the protocol: a **1.0** hub resolves URL Patterns against **its own** URL as base, so it only worked while the hub shared the API's host; a **0.x** hub compares `topic=` exactly and then as an anchored URI Template with no base resolution at all, so a relative selector never matched the absolute published IRI on any host. The item operation is the one API Platform builds the IRI from — the first non-collection operation whose method is `GET`, `HEAD` or `OPTIONS`, in declaration order (`ResourceMetadataCollection::getOperation()`), not the first variable-bearing one. Declaring the topic explicitly in the absolute form API Platform publishes remains the escape hatch for a topic of your own:

```php
#[AsDataTable(
    dataClass: Book::class,
    mercure: ['topics' => ['https://api.example.com/api/books/{id}']],
)]
```

Three more API Platform specifics: `@=iri(object)` is resolved explicitly to the item topic, any other `@=` expression topic is dropped with a logged warning naming it — resolution then continues to the item topic, so `@=iri(object.getOwner())` still ends up subscribing to the item IRI and the warning is what tells you the declared topic was not honored — `private: true` is mapped to `withCredentials` automatically (see Private topics below), and the subscriber cookie comes from symfony/mercure-bundle (`Authorization`, the `mercure()` Twig function), not from this bundle — and `@=` needs `symfony/expression-language` installed on the app side or API Platform throws when it publishes.

## What it does client-side

The Stimulus controller listens to the configured topics and, on each SSE message, dispatches `datatables:mercure:message` and calls `table.ajax.reload(null, false)` (debounced). The connection closes on controller `disconnect()`.

The subscription dialect follows the hub's protocol version, read server-side from `HubInterface::getProtocolVersion()` (symfony/mercure 0.8+) and serialized as `protocolVersion` only when it is not `0.x`. Nothing to configure in the bundle — the dialect follows the hub. A 1.0 hub that is sent `topic=` creates no subscription at all: the connection opens and nothing ever arrives, so check the protocol version before the topics when live updates stop after a hub upgrade.

- 0.x hub — one `topic=` query parameter per topic, with URI Template selectors.
- 1.0 hub — `match_urlpattern=` with `/books/{id}` rewritten to `/books/:p0` for a templated topic (the group name is generated: RFC 6570 variable names are not all valid URL Pattern group names — `:book.id` is not a group, `:1` throws — and a repeated variable would collide), `match=<topic>` for a plain one, and `match=` on the literal value for a topic using an RFC 6570 operator (`{?page}`), which has no URL Pattern equivalent. A group also matches the literal placeholder, so API Platform's `/api/books/{id}` publications keep matching.
- No reported version (older symfony/mercure) — legacy `topic=` parameters.

Publishing is protocol-agnostic: Mercure 1.0 kept the `topic=` form fields, so the publish path needs no change. Hub-side, a 1.0 hub needs `protocol_version: '1.0'` plus `jwt.claims` (RFC 9068 requires `iss`/`sub`/`client_id`), and `protocol_version_compatibility 8` keeps 0.x clients working while you migrate.

- Only reloads server-side tables — a table configured with static `data` will not auto-refresh from SSE.
- No Mercure config means no SSE subscription (dynamic import skipped).
- `withCredentials` forwards cookies/auth on the SSE request; auto-resolution turns it on for a resource marked `private` (see Private topics). On a 1.0 hub the cookie is `__Secure-mercure_access_token`, so private topics also need an HTTPS hub URL.

## Private topics

API Platform marks an update private with `#[ApiResource(mercure: ['private' => true])]`, and `PublishMercureUpdatesListener::buildUpdate()` passes that straight to `Symfony\Component\Mercure\Update`. A hub only delivers a private update to a subscriber whose token grants `subscribe` on one of the *update's* topics — authorization is evaluated against the update, never against the subscription's matchers — so an anonymous `EventSource` opens the connection and nothing ever arrives.

The bundle therefore reads `private` from the resource metadata and from every operation — any declaration wins, since the bundle cannot know which operation published a given update — and auto-resolution serializes `withCredentials: true`. It still sets no cookie and mints no token: that is symfony/mercure-bundle's job (`Authorization`, the `mercure()` Twig function). An explicit `withCredentials` keeps winning: on the attribute an array without a `topics` key still auto-resolves the topics and only overrides the subscription options (`mercure: ['withCredentials' => false]`), while `->mercure(withCredentials: false)` is manual configuration that never consults the auto-resolver and subscribes to the internal fallback topic unless the topics are passed with it. A resource without `private` (or with `private: false`) serializes the same payload as before.

The usual reason this still fails is the grant, not the bundle. On a Mercure 1.0 hub the cookie is `__Secure-mercure_access_token` (so an HTTPS hub URL is mandatory) and a `topics` entry is an object whose `match_type` defaults to `exact`; bare strings are rejected. A 0.x grant holding `https://example.com/api/books/{id}` is an exact match on that literal string, so it does not cover the published `https://example.com/api/books/42` and the hub answers `403 insufficient_scope`. Use a `urlpattern` grant: `new Grant(actions: [Grant::ACTION_SUBSCRIBE], topics: ['urlpattern' => ['https://example.com/api/books/:id']])` from `Symfony\Component\Mercure\Jwt` (0.8), minted by a factory configured for the dialect — `new LcobucciFactory($secret, protocolVersion: ProtocolVersion::V1)`. Under `V1` the token is an RFC 9068 access token, so `create()` throws unless `additionalClaims` carries all four of `iss`, `aud`, `sub` and `client_id`. A cross-origin hub also has to answer a credentialed request with `Access-Control-Allow-Credentials: true` and a concrete `Access-Control-Allow-Origin` — the wildcard is rejected. See the Mercure authorization concepts and upgrade guide.

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
