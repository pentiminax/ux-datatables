# Security & permissions

Three independent layers. Skipping any of them leaves a hole the other two do not cover.

1. **Firewall / `access_control`** — who may call `/datatables/ajax/*` at all. The bundle never does this for you.
2. **Bundle permission checks** — Symfony voters called at rendering, table Ajax resolution, row actions, and built-in mutations.
3. **CSRF** — session-backed mutation token on delete / inline-edit / edit-form.

## 1. Protect the Ajax routes (required)

The table token in a request identifies *which* table, it does **not** authenticate or authorize anyone. Importing the bundle routes (see `server-side.md`) exposes them to everyone unless you cover them:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/datatables/ajax, roles: ROLE_USER }
```

Routes: `/datatables/ajax/{data,templates,edit,delete,edit-form,edit-form/view,detail,export}`.

Keep this rule in sync with the page rendering the table — a table on an admin page whose data endpoint is public leaks the whole dataset.

## 2. Bundle-owned attributes

```php
use Pentiminax\UX\DataTables\Security\Permission;

Permission::DT_ACCESS_TABLE;       // table rendering + Ajax table resolution
Permission::DT_EXECUTE_ACTION;     // every Action::setPermission() check
Permission::DT_EDIT_ROW;           // edit modal + boolean toggle writes
Permission::DT_DELETE_ROW;         // delete mutation
Permission::DT_VIEW_ROW_DETAILS;   // collapsible detail rows
```

**Two families, do not confuse them.**

- `DT_ACCESS_TABLE` and `DT_EXECUTE_ACTION` are handled by the bundle's own `SecurityVoter`. It supports only those two and abstains on everything else, so it never competes with your voters: it reads the attribute you passed to `DataTable::setPermission()` / `Action::setPermission()` and relays Symfony's decision on *that* attribute. **You never register a voter for these two.**
- `DT_EDIT_ROW`, `DT_DELETE_ROW`, `DT_VIEW_ROW_DETAILS` are **yours**. No bundle voter supports them. With a firewall active and no voter granting them, Symfony denies — so every built-in mutation returns `403` until you write one.

Symfony's default `affirmative` strategy is correct here. Do **not** switch the application to `unanimous` for the bundle's sake: when your voter denies, the bundle voter denies too.

### Row permission matrix

Each row attribute is checked **in addition to** `DT_EXECUTE_ACTION` — they cumulate, neither replaces the other.

| Endpoint | Checks |
|----------|--------|
| Edit modal (view + submit) | `DT_EDIT_ROW` + `DT_EXECUTE_ACTION` |
| Delete | `DT_DELETE_ROW` + `DT_EXECUTE_ACTION` |
| Collapsible detail row | `DT_VIEW_ROW_DETAILS` + `DT_EXECUTE_ACTION` |
| Boolean toggle | `DT_EDIT_ROW` only — not an action; the column permission is evaluated separately by `BooleanMutationContextResolver` |

There is **no fallback**: `DT_VIEW_ROW_DETAILS` is required on a collapsible detail row even when the detail action carries its own `setPermission()`. An action with no `setPermission()` triggers no `DT_EXECUTE_ACTION` vote at all — its visibility belongs to `displayIf()` or the action column's permission, not to a security decision.

### Writing the row voter

```php
use Pentiminax\UX\DataTables\Security\Permission;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProductVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [
            Permission::DT_EDIT_ROW,
            Permission::DT_DELETE_ROW,
            Permission::DT_VIEW_ROW_DETAILS,
        ], true) && $subject instanceof Product;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $subject->getOwner()?->getId() === $user->getId();
    }
}
```

The subject is always the **located Doctrine entity**.

## 3. Table-level permission

```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table->setPermission('PRODUCT_TABLE_VIEW')->serverSide()->processing();
}
```

Accepts a `string` attribute or a `Symfony\Component\ExpressionLanguage\Expression` (needs `symfony/expression-language`). Enforced in two places, both throwing `AccessDeniedException`:

- `render_datatable()` — *before* the table prepares its rows, so a client-side table never fetches data it may not show.
- `AjaxDataTableRegistry` token resolution — covers every Ajax route, data and mutations alike.

The voter receives the `AbstractDataTable` instance as subject.

## 4. Action & column permissions

`Action::setPermission(string|Expression $attribute, ?callable $subjectResolver = null)`:

- **Static** (no resolver) — evaluated once before serialization; ungranted actions are removed entirely and the attribute never reaches the browser. Use for role checks: `->setPermission('ROLE_ADMIN')`.
- **Per-row** (with resolver) — evaluated per row; the resolver returns the voter subject: `->setPermission('EDIT_PRODUCT', fn ($row) => $row)`.

**What the resolver receives differs by call site** — the single sharpest gotcha here:

- At render time: the row source passed to the rendering pipeline (`RowContext::$source`). That is the entity under a page projector, but a plain **array** if the provider hydrates arrays (a `customizeQueryBuilder()` narrowing the `select`, for instance).
- On the `delete` / `edit-form` / `detail` Ajax endpoints: always the **entity located by id**, never a raw array row.

A resolver used on both paths must tolerate either shape, or the action should use a static permission instead.

`AbstractColumn::setPermission()` exists too, but **static form only** — no resolver.

Ungranted per-row actions are reported to the browser in `__ux_datatables_denied_actions` so the renderer can hide them; the server re-checks on every mutation endpoint regardless, so a tampered client gains nothing.

## 5. CSRF and mutations

Delete, inline boolean toggle, and edit-form submit validate a session-backed token (`MutationTokenValidator`, `X-CSRF-Token` header). Without a session the payload carries `mutationsEnabled: false` and the controls are disabled client-side.

Custom actions (`Action::new(...)->asAjaxRequest($csrfTokenId)`) post to **your** route with their own per-action token. `Action::setPermission()` only decides whether the button is rendered — your route still needs `#[IsGranted]` and its own CSRF validation. Same for API Platform endpoints: bundle row voters only run for rows the bundle backend processes.

## Gotchas

- **Everything is granted with no security stack.** `Security\AuthorizationChecker` wraps `AuthorizationCheckerInterface` with `nullOnInvalid()`; with no firewall (CLI, unit tests, an app without `symfony/security-bundle` enabled) every check returns `true`. Don't read a green test suite as proof the voters run.
- **`DuplicateActionNameException`.** Two action columns declaring the same action name (only reachable by hand-assembling two `ActionColumn::fromActions()` collections) now throw instead of silently overwriting the row's resolved action data.
- **Renaming from pre-hardening code.** The old `EDIT` / `DELETE` / `VIEW` attributes are gone — a voter still supporting those grants nothing. Rename to the `Permission::DT_*` constants.
- **`EntityMutator::delete()` requires an `Action`.** Direct callers must pass the resolved delete action; it is what carries the `DT_EXECUTE_ACTION` re-check.

## Cross links

- `references/actions.md` — action configuration, collapsible detail rows.
- `references/columns.md` — column-level `setPermission()`.
- `references/server-side.md` — importing the Ajax routes these rules protect.
- `docs/src/content/docs/getting-started/security.mdx` — full reference.
