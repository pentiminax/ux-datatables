---
name: ux-datatables
description: Use when building, configuring, or debugging DataTables with the pentiminax/ux-datatables Symfony bundle — defining DataTable classes, columns, client-side vs server-side mode, extensions, row actions, or API Platform / Mercure integration.
---

# UX DataTables

## Overview

`pentiminax/ux-datatables` integrates [DataTables.net](https://datatables.net) into Symfony. You declare a class extending `AbstractDataTable`, annotate it with `#[AsDataTable(Entity::class)]`, define columns, and render with the Twig `render_datatable()` function. A Stimulus controller (`@pentiminax/ux-datatables/datatable`) lazy-loads DataTables and its extensions.

## Decision tree: client-side vs server-side

```
Dataset < ~5k rows AND can load all upfront?
├── yes → CLIENT-SIDE: define columns, optionally setData()/data(). Browser paginates/filters/sorts.
└── no  → SERVER-SIDE: configureDataTable(fn) → $table->serverSide()->processing()
          DB handles paging/filter/sort. Doctrine provider auto-wired from #[AsDataTable(Entity::class)].
          MUST import bundle routes (see references/server-side.md).
```

## Minimal example

```php
use App\Entity\User;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\{DateColumn, NumberColumn, TextColumn};
use Pentiminax\UX\DataTables\Model\{AbstractDataTable, Action, Actions, DataTable};

#[AsDataTable(User::class)]
final class UserDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [
            NumberColumn::new('id', 'ID'),
            TextColumn::new('email', 'Email'),
            DateColumn::new('createdAt', 'Created')->setFormat('d/m/Y'),
        ];
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->serverSide()->processing()->pageLength(25);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(Action::edit())->add(Action::delete()->askConfirmation('Delete?'));
    }
}
```

Controller + Twig:
```php
public function index(UserDataTable $table, Request $request): Response
{
    $table->handleRequest($request);
    if ($table->isRequestHandled()) {
        return $table->getResponse();
    }
    return $this->render('user/index.html.twig', ['table' => $table]);
}
```
```twig
{{ render_datatable(table) }}
```

Scaffold from an entity: `php bin/console make:datatable`.

## References (read on demand)

- `references/defining-a-datatable.md` — `AbstractDataTable`, `#[AsDataTable]`, the `configure*()` hooks, data providers, `customizeQueryBuilder()`, page projection (`projectPage()`).
- `references/columns.md` — all 11 column types + shared `AbstractColumn` methods.
- `references/server-side.md` — server-side wiring, route import, Stimulus events, custom Ajax, computed columns (`setOrderExpression()`).
- `references/extensions.md` — Buttons, Select, Responsive, ColumnControl, Scroller, KeyTable, ColReorder, FixedColumns.
- `references/actions.md` — row actions, permissions, conditional display.
- `references/api-platform.md` — API Platform + Mercure integration (both opt-in).
- `references/gotchas.md` — common mistakes and fixes.

## Common mistakes (see gotchas.md)

1. Server-side actions do nothing → bundle routes not imported.
2. Server-side with no Doctrine data → missing `entityClass` in `#[AsDataTable]`.
3. API Platform / Mercure behavior absent → must opt in explicitly (`apiPlatform: true`, `mercure()`).
