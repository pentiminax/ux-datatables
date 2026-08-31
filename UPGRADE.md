# Upgrade Guide

Each section covers one version bump. When you skip versions, apply every section between your
current version and the target, oldest first.

## v0.82 → v0.83

Affects custom `QueryFilterInterface` implementations, code that implemented
`DataTableQueryIntentFactoryInterface`, and code that touched `DataTableInfrastructure` or the
internal query/row-pipeline classes listed below. Table configuration, columns, Twig templates, the
Ajax routes, and the serialized frontend payload are unchanged. If your project only configures
tables through `AbstractDataTable`, there is nothing to do.

### `DataTableQueryIntent` is flat

The per-concern intent DTOs are gone. A custom `QueryFilterInterface` reads the same values
directly off `$context->intent`.

```php
// before
$offset    = $context->intent->pagination->offset;
$limit     = $context->intent->pagination->limit;
$search    = $context->intent->globalSearch?->value;
$column    = $context->intent->order?->column;
$direction = $context->intent->order?->direction->value;

foreach ($context->intent->columnSearches as $columnSearch) {
    $name  = $columnSearch->column->getName();
    $value = $columnSearch->value;
}

// after
$offset    = $context->intent->offset;
$limit     = $context->intent->limit;
$search    = $context->intent->globalSearch;
$column    = $context->intent->orderColumn;
$direction = $context->intent->orderDir;

foreach ($context->intent->columnSearches as $columnSearch) {
    $name  = $columnSearch['column']->getName();
    $value = $columnSearch['value'];
}
```

What changed:

- `PaginationIntent` is inlined as `offset` (`int`) and `limit` (`?int`)
- `GlobalSearchIntent` is inlined as `globalSearch` (`?string`); an absent search is `null`
- `OrderIntent` is inlined as `orderColumn` (`?ColumnReadReference`) and `orderDir`
  (`'asc'|'desc'|null`, a plain string instead of the `SortDirection` enum). `SortDirection` is
  removed; compare against `'asc'`/`'desc'`
- `ColumnSearchIntent` is replaced by `array{column: ColumnReadReference, value: string}`; read
  array keys instead of properties
- `regexRequested` is gone from both search shapes. The bundle's own filters never applied it. If a
  filter of yours read it, resolve regex intent from `DataTableRequest` before the query pipeline
  runs
- `draw`, `columns`, and `columnControls` are unchanged

### `DataTableQueryIntentFactoryInterface` is removed

Intent creation is no longer an extension point. `DefaultDataTableQueryIntentFactory` is the
concrete collaborator, and `QueryFilterPipeline` depends on it directly.

- an autowired `DataTableQueryIntentFactoryInterface` argument no longer resolves; type-hint
  `DefaultDataTableQueryIntentFactory`
- a class of yours implementing the interface must be dropped. Express request-shaping in a
  `QueryFilterInterface` implementation instead, which stays a public contract

### `DataTableInfrastructure` accessors are properties

The collaborator getters are gone; the promoted properties are public readonly.

```php
// before
$infrastructure->columnResolver();
$infrastructure->queryFilterPipeline();

// after
$infrastructure->columnResolver;
$infrastructure->queryFilterPipeline;
```

`columnResolver`, `renderingPreparer`, `runtimeFactory`, `queryIntentFactory`,
`queryFilterPipeline`, and `profiler` are affected. `builder()` stays a method, because it lazily
creates the builder.

### Removed internal classes

These had a single consumer each and are inlined into their caller. None of them is a documented
extension point, and none had a public service alias.

| Removed | Replacement |
| --- | --- |
| `Query\Builder\QueryFilterChain` | `Query\Builder\QueryFilterPipeline`, which owns the filter order and the `resetParamIndexScope()` call between filters |
| `DataProvider\DataProviderResolver` | `DataProvider\AutoDataProviderFactory`, injected straight into `DataTableRuntimeFactory` |
| `RowMapper\Stage\UrlColumnResolutionStage` | `RowProcessingPipeline` calls `UrlColumnDataResolver` |
| `RowMapper\Stage\TemplateRenderingStage` | `RowProcessingPipeline` calls `TemplateColumnRenderer` |
| `RowMapper\Stage\ActionResolutionStage` | `RowProcessingPipeline` calls `ActionRowDataResolver` |

`RowStageInterface` and `RowProcessingPipeline::add()` are untouched: a custom stage of yours keeps
working. Only these three built-in stage classes disappear, and the behavior they performed still
runs in the same order inside the pipeline.

## v0.80 → v0.81

Only `TemplateColumn` is affected. If you do not use it, there is nothing to do.

### `TemplateColumn` Twig `row` is the `mapRow()` object

In a `TemplateColumn` template, `row` used to be the **array** returned by `mapRow()`. It is now
the **object** passed to `mapRow()` — the projected DTO when `projectPage()` is active, otherwise
the source object. The array is still available, under the new `payload` key.

```twig
{# before #}
<span class="badge">{{ row.status }}</span>
{{ row.fullName }}

{# after #}
<span class="badge">{{ data }}</span>   {# this cell's value #}
{{ payload.fullName }}                  {# any other mapped key #}
{{ row.getFullName() }}                 {# or read the object directly #}
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

Collapsible detail rows (`Action::collapsible()`) and edit modals also expose an `entity`
variable. That one is unrelated to `TemplateColumn`, is not an alias, and is not deprecated — leave
those templates alone.

Do not branch on `payload` for authorization or visibility. On the API Platform render route the
rows are posted by the browser, so `payload` is client-controlled input; read `row` or `source`
instead.

### Reserved `TemplateColumn` template parameters now throw

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
