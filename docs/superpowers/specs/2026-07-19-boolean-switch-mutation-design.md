# Boolean switch mutation design

## Goal

Make `BooleanColumn::renderAsSwitch()` work when the entity identifier is not a displayed DataTable column, and stop exposing or trusting an entity class in browser-controlled mutation payloads.

The browser must identify the registered DataTable with an opaque signed token. The backend resolves that token to the DataTable instance and derives the entity class and allowed boolean field from its configuration.

## Current failures

`DefaultRowMapper` maps object rows using only configured columns. When a table does not declare `id`, the boolean renderer reads an absent `row.id` and produces `data-id=""`. The controller then rejects the interaction with `Missing ID or field for boolean switch update`.

The current browser contract also includes `data-entity` and sends `entity` to `/datatables/ajax/edit`. Although the backend checks Doctrine metadata, writability, CSRF, and permissions, the client still chooses the Doctrine class to mutate.

## Chosen architecture

### Signed DataTable identity

Reuse `AjaxDataTableRegistry` and `AjaxDataTableTokenManager` rather than introducing another resolver or cryptographic format.

The value is a signed HMAC token, not encrypted data. Confidentiality is unnecessary because the browser already receives DataTable configuration; authenticity is the required property. The token prevents a client from inventing an arbitrary DataTable class while allowing the backend to resolve only registered `AbstractDataTable` services.

The rendered DataTable payload will expose the token as a mutation-specific value. The boolean switch request will send this token as `dataTable`, replacing both `entity` and `dataTableClass` for this endpoint.

### Backend mutation context

Introduce a focused boolean-mutation context resolver. `AjaxEditController` passes `payload.dataTable` and `payload.field` to this resolver, which uses `AjaxDataTableRegistry::get()`. An unknown or invalid token is rejected before entity lookup or mutation.

From the resolved `AbstractDataTable`, the resolver obtains:

- the entity class through `AbstractDataTable::getEntityClass()`;
- the configured columns through the initialized DataTable;
- the matching `BooleanColumn` and its effective toggle field.

The requested field must match a configured `BooleanColumn` whose switch rendering is enabled. This prevents the endpoint from mutating an unrelated mapped boolean property merely because the browser supplied its name.

`EntityMutator::setProperty()` remains responsible for locating the entity, checking `EDIT` permission, validating Doctrine boolean metadata and writability, flushing, and publishing Mercure updates. It receives only server-resolved values.

### Row identifier metadata

Add a row-processing stage dedicated to boolean mutations. For every switch-enabled `BooleanColumn`, it extracts the configured identifier (`toggleIdField`, default `id`) from the original source row, including `RowContext::source` when a page projector is active.

The stage writes identifiers under a reserved internal row key, separate from user columns. The metadata is keyed by the boolean column identity so multiple switches with different identifier fields can coexist.

The TypeScript renderer reads this metadata first and keeps `row[toggleIdField]` as a fallback for external/API Platform rows that bypass the PHP row pipeline. Missing identifiers render a disabled switch rather than an interactive control that can only fail.

### Frontend contract

The switch markup contains only the mutation values needed by the browser:

- row identifier;
- configured boolean field;
- method and endpoint.

It no longer contains `data-entity`. The change handler obtains the signed DataTable token from the controller payload, rejects a missing token locally, and sends:

```json
{
  "id": 42,
  "field": "active",
  "newValue": true,
  "dataTable": "opaque-hmac-token"
}
```

The DTO no longer accepts an entity class or a raw DataTable class for boolean mutations.

## Error handling

- Missing row identifier: render the switch disabled.
- Missing DataTable token in the page payload: render mutations disabled or revert the interaction without issuing a request.
- Invalid or unknown token: reject the request as a bad request before accessing Doctrine.
- Missing entity class on the resolved DataTable: reject the request as a configuration error.
- Field absent from the resolved switch-enabled BooleanColumns: reject as a non-toggleable field.
- Existing CSRF, permission, Doctrine-type, writability, not-found, conflict, and persistence mappings remain unchanged.

## Compatibility

This intentionally changes the boolean-toggle AJAX contract. There is no fallback to client-provided `entity` because retaining it would preserve the trust boundary being removed.

`BooleanColumn::setEntityClass()` and the automatic entity-class injection are no longer needed by switch rendering. Their public removal must follow the package's compatibility policy. If immediate removal is too disruptive, they may remain as deprecated no-op configuration for one release, but `entityClass` must not be serialized to the browser.

Action and delete mutations are outside this change unless they share the same boolean-specific transport. Their existing behavior is not silently altered.

## Verification

Regression tests will prove:

1. A server-side entity whose `id` is not a configured column still produces usable boolean mutation metadata.
2. Custom string/UUID identifier fields are preserved.
3. A missing identifier disables the switch.
4. Switch HTML contains no `data-entity`.
5. The JavaScript request contains the signed DataTable token and no entity or raw DataTable class.
6. The controller resolves the correct registered DataTable and derives its entity class.
7. Invalid tokens and fields not configured as switch-enabled BooleanColumns are rejected without mutation.
8. Existing CSRF, permission, Doctrine boolean validation, Mercure publication, and persistence-error behavior still pass.

After targeted PHP and TypeScript tests, run the full PHPUnit suite, frontend tests/type checks/build, code style/static analysis where configured, and regenerate `assets/dist`.

## Scope boundaries

- Composite Doctrine identifiers remain unsupported because the mutation DTO accepts one scalar identifier.
- API Platform IRI-only identifiers retain the renderer fallback and may require a separate normalization enhancement if no scalar identifier is present.
- No new dependency or cryptographic implementation is introduced.
