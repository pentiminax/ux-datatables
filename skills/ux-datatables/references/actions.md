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
| `Action::edit($label='Edit', $class='btn btn-warning')` | Edit | warning | opens the edit modal |
| `Action::delete($label='Delete', $class='btn btn-danger')` | Delete | danger | deletes the row via Ajax |
| `Action::detail($label='Detail', $class='btn btn-primary')` | Detail | primary | links to a detail page |

Only one action per type is kept (`Actions::add()` keys by type).

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
    ->permission('EDIT', fn (User $u) => $u->getId() !== 1);  // see below
```

> `Action` has `linkToUrl()` only — there is no `linkToRoute()` on actions. For route-based links, build the URL in the callable, or use a `UrlColumn` (which does support `linkToRoute()`).

## Permissions: static vs per-row

`permission(string $attribute, ?callable $subjectResolver = null)`:

- **Static** (no resolver) — evaluated once before serialization. If not granted, the action is removed entirely. Use for role checks: `->permission('ROLE_ADMIN')`.
- **Per-row** (with resolver) — evaluated per row; the resolver receives the raw row and returns the voter subject: `->permission('EDIT', fn ($row) => $row)`.

Same model applies to columns (`AbstractColumn::permission()`), but columns only support the static form.
