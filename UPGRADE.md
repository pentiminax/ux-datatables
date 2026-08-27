# Upgrade Guide

## `TemplateColumn` Twig `row` is the `mapRow()` object

In a `TemplateColumn` template, `row` used to be the **array** returned by `mapRow()`. It is now
the **object** passed to `mapRow()` — the projected DTO when `projectPage()` is active, otherwise
the source object. The array is still available, under the new `payload` key.

```twig
{# before #}
<span class="badge">{{ row.status }}</span>
{{ row.fullName }}

{# after #}
<span class="badge">{{ data }}</span>          {# this cell's value #}
{{ payload.fullName }}                          {# any other mapped key #}
{{ row.getFullName() }}                         {# or read the object directly #}
```

`payload` is the exact value `row` used to hold, so a template that only read mapped keys migrates
with a mechanical `row` → `payload` rename. As before, it does not contain cells rendered by other
`TemplateColumn`s, nor the row action metadata.

What changed:

- `row` is the object passed to `mapRow()`; read domain properties from it
- `payload` is the array returned by `mapRow()`; use it for a mapped key other than this cell's
- `data` is unchanged: the resolved value of this cell
- `source` is unchanged: the original hydrated object, same reference as `row` without a projector
- `entity` still works as a deprecated alias of `row`, for `TemplateColumn` templates only. It will
  be removed in a later release
- `item` is no longer provided. It used to be a second alias of `row`; pass it yourself through
  `setTemplate()` parameters if a template of yours needs that name

Collapsible detail rows (`setCollapsibleTemplate()`) and edit modals also expose an `entity`
variable. That one is unrelated to `TemplateColumn`, is not an alias, and is not deprecated — leave
those templates alone.

Do not branch on `payload` for authorization or visibility. On the API Platform render route the
rows are posted by the browser, so `payload` is client-controlled input; read `row` or `source`
instead.

## Reserved `TemplateColumn` template parameters now throw

Passing a reserved context key through `setTemplate()` used to be silently dropped, which made a
collision render wrong HTML with no signal. It now fails at configuration time.

```php
use Pentiminax\UX\DataTables\Column\TemplateColumn;

// throws InvalidArgumentException
TemplateColumn::new('status_display')
    ->setTemplate('datatable/columns/status.html.twig', ['row' => $somethingElse]);
```

What changed:

- `setTemplate()` throws `InvalidArgumentException` when `$parameters` uses `row`, `source`,
  `payload`, `data`, `column`, or `entity`
- `payload` is newly reserved; rename such a parameter to something else
- any other key still passes through untouched, `item` included
