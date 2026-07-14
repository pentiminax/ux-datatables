# Row actions

Define per-row buttons in `configureActions()`. The bundle appends a generated `ActionColumn` automatically when at least one action is added.

```php
use Pentiminax\UX\DataTables\Model\{Action, Actions};

public function configureActions(Actions $actions): Actions
{
    return $actions
        ->setColumnLabel('Operations')          // header (default: 'Actions')
        ->setColumnClassName('text-end')
        ->add(Action::edit())
        ->add(
            Action::delete('Delete')
                ->icon('bi bi-trash')
                ->askConfirmation('Delete this row?')
                ->permission('ROLE_ADMIN')
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
names throw `InvalidArgumentException` while configuring the table.

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
    ->permission('EDIT', fn (User $u) => $u->getId() !== 1);  // see below
```

> `Action` has `linkToUrl()` only — there is no `linkToRoute()` on actions. For route-based links, build the URL in the callable, or use a `UrlColumn` (which does support `linkToRoute()`).

## Permissions: static vs per-row

`permission(string $attribute, ?callable $subjectResolver = null)`:

- **Static** (no resolver) — evaluated once before serialization. If not granted, the action is removed entirely. Use for role checks: `->permission('ROLE_ADMIN')`.
- **Per-row** (with resolver) — evaluated per row; the resolver receives the raw row and returns the voter subject: `->permission('EDIT', fn ($row) => $row)`.

Same model applies to columns (`AbstractColumn::permission()`), but columns only support the static form.

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

- Loaded lazily via `GET /datatables/ajax/detail` (frontend `fetchDetailRow`). **Requires the bundle routes imported** — same import as server-side (see `server-side.md`); without it the toggle does nothing.
- `collapsible()` is meaningful only on `Action::detail()`. It is mutually exclusive with `linkToUrl()` in practice (an expand toggle, not a link).
- Template example: `<div>{{ entity.email }}</div>` — `entity` is the resolved Doctrine entity / source row.

## Column position & alignment

`ActionsPosition` (`Pentiminax\UX\DataTables\Enum\ActionsPosition`): `BeforeColumns` (`'before'`), `AfterColumns` (`'after'`, default).

- **Collection-level**: `Actions::position(ActionsPosition::BeforeColumns)` places the whole actions column before/after the data columns.
- **Per-action**: `Action::position(...)` overrides one action only; `null` (default) inherits the collection position.
- **Two-column split**: when actions resolve to both positions, **two** columns are produced — `actions_before` (prepended) and `actions` (appended). Typical use: pin a collapsible detail toggle before the data, keep edit/delete after.
- `position` is a server-side layout concern only — **not** serialized into the client JSON.

`Actions::alignment(ActionsAlignment)` horizontally aligns the action cell. `ActionsAlignment`: `Left`/`Center`/`Right`, applied as a `dt-{value}` CSS class (e.g. `Center` → `dt-center`). Method name is literally `alignment` — use verbatim.
