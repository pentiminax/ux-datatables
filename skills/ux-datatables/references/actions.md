# Row actions

Define per-row buttons in `configureActions()`. The bundle appends a generated `ActionColumn` automatically when at least one action is added.

```php
use Pentiminax\UX\DataTables\Model\{Action, Actions};

public function configureActions(Actions $actions): Actions
{
    return $actions
        ->setColumnLabel('Operations')          // header (default: 'Actions')
        ->setColumnClassName('text-end')
        ->setColumnControl(['colvisDropdown'])  // requires ColumnControlExtension on the table
        ->add(Action::edit())
        ->add(
            Action::delete('Delete')
                ->icon('bi bi-trash')
                ->askConfirmation('Delete this row?')
                ->setPermission('ROLE_ADMIN')
        );
}
```

## Factory methods (`src/Model/Action.php`)

| Factory | Default label | Default class | Behavior |
|---------|---------------|---------------|----------|
| `Action::edit($label='Edit', $class='btn btn-warning')` | Edit | warning | opens the inline edit modal (zero-config with `symfony/form`, no Bootstrap required) |
| `Action::delete($label='Delete', $class='btn btn-danger')` | Delete | danger | deletes the row via Ajax |
| `Action::detail($label='Detail', $class='btn btn-primary')` | Detail | primary | links to a detail page, or expands a collapsible child row (see below) |
| `Action::new($name, $label='', $class='')` | — | — | renders a custom link action |

Every action name must be non-empty and unique within the collection. Custom names cannot use the
reserved native names `DELETE`, `DETAIL`, `EDIT`, or `CUSTOM` (case-insensitive). Invalid or duplicate
names throw `InvalidArgumentException` while configuring the table. Uniqueness must also hold
*across* action columns — hand-assembling two `ActionColumn::fromActions()` collections that share a
name throws `DuplicateActionNameException` at render or on the Ajax endpoints.

## Fluent configuration

```php
Action::detail()
    ->setLabel('View')
    ->setClassName('btn btn-sm btn-primary')
    ->setIcon('bi bi-eye')
    ->setIdField('uuid')                       // ID field used in URLs (default 'id')
    ->setHtmlAttributes(['data-turbo' => 'false'])
    ->askConfirmation('Sure?')                 // confirmation button label
    ->displayIf('status', 'draft')             // show only when row.status === 'draft'
    ->linkToUrl(fn (User $u) => '/users/'.$u->getId())  // string | callable
    ->setEntityClass(User::class)              // entity for permission subject
    ->collapsible('detail.html.twig', [...])   // detail-only: expand into a child row (see below)
    ->position(ActionsPosition::BeforeColumns) // pin THIS action's column (null = inherit collection)
    ->setPermission('EDIT', fn (User $u) => $u->getId() !== 1);  // see below
```

> `Action` has `linkToUrl()` only — there is no `linkToRoute()` on actions. For route-based links, build the URL in the callable, or use a `UrlColumn` (which does support `linkToRoute()`).

## Permissions: static vs per-row

`setPermission(string|Expression $attribute, ?callable $subjectResolver = null)`:

- **Static** (no resolver) — evaluated once before serialization. If not granted, the action is removed entirely. Use for role checks: `->setPermission('ROLE_ADMIN')`.
- **Per-row** (with resolver) — evaluated per row; the resolver returns the voter subject: `->setPermission('EDIT_PRODUCT', fn ($row) => $row)`. **The value it receives differs by call site**: the row source at render time (an array if the provider hydrates arrays), the located entity on the `delete` / `edit-form` / `detail` endpoints.

Same model applies to columns (`AbstractColumn::setPermission()`), but columns only support the static form.

`setPermission()` alone is **not** enough to secure a mutation. The built-in endpoints also require
your own voter on `Permission::DT_EDIT_ROW`, `DT_DELETE_ROW`, or `DT_VIEW_ROW_DETAILS` — they
cumulate, neither replaces the other. See `references/security.md` for the full matrix, the
table-level `DataTable::setPermission()`, and the Ajax route protection these checks assume.

Delete actions and inline boolean toggles require an active session for CSRF protection. In a
stateless or session-less rendering context, the payload exposes `mutationsEnabled: false` and the
corresponding controls are disabled.

## Collapsible detail rows

`Action::detail()->collapsible($template, $parameters = [])` turns the detail action into an expand/collapse arrow. Clicking it lazily fetches `$template` and injects the result as a DataTables child row. The template receives the located row as `entity`, plus any extra `$parameters`.

```php
use Pentiminax\UX\DataTables\Enum\ActionsPosition;
use Pentiminax\UX\DataTables\Model\{Action, Actions};

public function configureActions(Actions $actions): Actions
{
    return $actions
        ->add(
            Action::detail('')
                ->icon('fa-solid fa-eye')
                ->position(ActionsPosition::BeforeColumns)
                ->collapsible('data_tables/details.html.twig')
        )
        ->add(Action::edit())
        ->add(Action::delete());
}
```

- Loaded lazily via `POST /datatables/ajax/detail` (frontend `fetchDetailRow`). **Requires the bundle routes imported** — same import as server-side (see `server-side.md`); without it the toggle does nothing.
- `collapsible()` is meaningful only on `Action::detail()`. It is mutually exclusive with `linkToUrl()` in practice (an expand toggle, not a link).
- Template example: `<div>{{ entity.email }}</div>` — `entity` is the resolved Doctrine entity / source row.

## Column position & alignment

`ActionsPosition` (`Pentiminax\UX\DataTables\Enum\ActionsPosition`): `BeforeColumns` (`'before'`), `AfterColumns` (`'after'`, default).

- **Collection-level**: `Actions::position(ActionsPosition::BeforeColumns)` places the whole actions column before/after the data columns.
- **Per-action**: `Action::position(...)` overrides one action only; `null` (default) inherits the collection position.
- **Two-column split**: when actions resolve to both positions, **two** columns are produced — `actions_before` (prepended) and `actions` (appended). Typical use: pin a collapsible detail toggle before the data, keep edit/delete after.
- `position` is a server-side layout concern only — **not** serialized into the client JSON.

`Actions::alignment(ActionsAlignment)` horizontally aligns the action cell. `ActionsAlignment`: `Left`/`Center`/`Right`, applied as a `dt-{value}` CSS class (e.g. `Center` → `dt-center`). Method name is literally `alignment` — use verbatim.

## Bulk actions (`src/Model/BulkAction.php`)

Define them in `configureBulkActions()`. They act on the rows the user selected, not on one row.

```php
use Pentiminax\UX\DataTables\Model\{BulkAction, BulkActions};
use Pentiminax\UX\DataTables\Mutation\{BulkActionContext, BulkRecords};

public function configureBulkActions(BulkActions $actions): BulkActions
{
    return $actions
        ->selectCurrentPageOnly()               // optional: forbid select-all across pages
        ->add(
            BulkAction::new('approve', 'Approve')
                ->icon(Icon::Check)
                ->askConfirmation('Approve {count} orders?')   // {count} substituted client-side
                ->setPermission('ORDER_APPROVE', fn (Order $o) => $o)
                ->chunk(250)                                   // rows loaded and flushed per batch
                ->handler(function (BulkRecords $records, BulkActionContext $ctx): void {
                    foreach ($records as $order) {
                        $this->approver->approve($order);
                    }
                })
        );
}
```

- UI: a **Bulk actions** button in the `topEnd` layout cell (disabled until a row is checked) opens a dropdown of the actions; a full-width band under the toolbar row shows the count, `Select all {count}` and `Deselect all`. Move it with `BulkActions::position()` (default `topEnd`).
- Declaring one auto-enables `SelectExtension` in `MULTI` style with checkboxes, and forces a `DT_RowId` on every row. A single Doctrine identifier is detected automatically; use `BulkActions::setIdField()` for another source. A configured `SelectStyle::SINGLE` throws.
- Every bulk action must declare a `handler()`; executing an action without one throws a configuration error.
- `BulkRecords` is lazy and single-pass: `count()` is the **selected** count, `$ctx->processedCount()` / `$ctx->skippedCount()` are the real ones, read after iterating.
- Per-row permission denials are skipped and counted; they do not abort the run.
- `allMatching` (select every row matching the current filters) needs a provider implementing `IdentifierCollectingDataProviderInterface` — `DoctrineDataProvider` does. Otherwise the endpoint answers `400`.
- Endpoint: `POST /datatables/ajax/bulk`, token in the body, CSRF in the `X-CSRF-Token` header. A run is **not** one transaction: one flush per chunk.
