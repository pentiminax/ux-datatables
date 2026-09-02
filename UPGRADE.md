# Upgrade Guide

Each section covers one version bump. When you skip versions, apply every section between your
current version and the target, oldest first.

## v0.83 → v0.84

Affects applications that built tables with `DataTableBuilderInterface` in a controller, passed a
bare `DataTable` to `render_datatable()`, or constructed `DataTableInfrastructure` themselves, and
code that implemented or type-hinted one of the removed single-implementation interfaces, custom
columns implementing `ColumnInterface` directly, or callers of `Query\SearchPredicateFactory`.
Tables already declared as `AbstractDataTable` classes are unchanged, as are columns, filters, Twig
templates, the Ajax routes, and every JSON payload on the wire.

### `DataTableBuilderInterface` and `DataTableBuilder` are removed

There is now exactly one way to define a table: a class extending `AbstractDataTable`. The Twig
function `render_datatable()` accepts only an `AbstractDataTable`; passing a bare `DataTable` throws
a `TypeError`.

`DataTableInfrastructure` carries the `data_tables` bundle defaults itself. Its `builder()` method
is gone, replaced by `createDataTable(string $id)`, and its constructor takes the defaults as three
arrays (`$options`, `$attributes`, `$extensions`) where the builder used to sit.

The table ID is the short name of the table class, so `UsersDataTable` renders as
`<table id="UsersDataTable">`. Two tables on the same page must not share a class name.

```php
// before
namespace App\Controller;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Contracts\DataTableBuilderInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'app_users')]
    public function index(DataTableBuilderInterface $builder): Response
    {
        $table = $builder
            ->createDataTable('usersTable')
            ->columns([
                TextColumn::new('firstName', 'First name'),
                TextColumn::new('lastName', 'Last name'),
            ])
            ->data([
                ['firstName' => 'John', 'lastName' => 'Doe'],
            ]);

        return $this->render('user/index.html.twig', ['table' => $table]);
    }
}
```

```php
// after
namespace App\DataTables;

use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;

final class UsersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('firstName', 'First name');
        yield TextColumn::new('lastName', 'Last name');
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->data([
            ['firstName' => 'John', 'lastName' => 'Doe'],
        ]);
    }
}
```

```php
// after
namespace App\Controller;

use App\DataTables\UsersDataTable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserController extends AbstractController
{
    #[Route('/users', name: 'app_users')]
    public function index(UsersDataTable $table): Response
    {
        return $this->render('user/index.html.twig', ['table' => $table]);
    }
}
```

When the rows are only known at request time, keep `configureDataTable()` for the options and call
`$table->setData($rows)` in the controller.

### Single-implementation interfaces are removed

Each of these interfaces had exactly one implementation. Type-hint the concrete class instead; it is
no longer `final`, so a test double or a subclass of your own still works.

| Removed interface | Type-hint instead |
| --- | --- |
| `Contracts\ColumnAutoDetectorInterface` | `ApiPlatform\ColumnAutoDetector` |
| `Contracts\EditModalTemplateResolverInterface` | `Form\EditModalTemplateResolver` |
| `Contracts\PermissionAwareColumnInterface` | `Contracts\ColumnInterface` |
| `Contracts\LayoutAwareExtensionInterface` | `Model\Extensions\ButtonsExtension` |

`PermissionAwareColumnInterface` was a one-method contract on top of `ColumnInterface`, and every
column already carried it. `getPermission(): ?string` now lives on `ColumnInterface` itself, so an
`instanceof` check before reading it is no longer needed. Columns extending `AbstractColumn` are
unaffected. A class of yours implementing `ColumnInterface` directly must add the method; return
`null` when the column is always visible.

`LayoutAwareExtensionInterface` was a marker for extensions injected into the DataTables `layout`
configuration rather than serialized as top-level options. `ButtonsExtension` is the only
layout-aware extension, so the marker is gone and the two call sites test for that class directly.
The profiler's `layoutAware` flag keeps its name and meaning.

The service aliases for the two resolver interfaces are gone. `ApiPlatform\ColumnAutoDetector` is
aliased in its place, so an autowired argument keeps resolving; point a decoration or an explicit
argument at the concrete class. `Form\EditModalTemplateResolver` has no alias: reference the
`datatables.form.edit_modal_template_resolver` service id.

### `SearchPredicateFactory` is merged into `DefaultSearchPredicateBuilder`

`Query\SearchPredicateFactory` was a static one-method class that
`Query\DefaultSearchPredicateBuilder` forwarded to unchanged. The type-dispatch logic now lives in
the builder, and the factory is removed.

```php
// before
$predicate = SearchPredicateFactory::build($qb, $column, $alias, $field, $value, $paramName, $forceNumeric);

// after
$predicate = (new DefaultSearchPredicateBuilder())->build($qb, $column, $alias, $field, $value, $paramName, $forceNumeric);
```

`Query\Strategy\ContainsSearchStrategy` now takes the builder as an optional constructor argument
defaulting to `DefaultSearchPredicateBuilder`, so `new ContainsSearchStrategy()` keeps working.

`SearchPredicateBuilderInterface` is untouched: it stays the supported seam for the
`AbstractDataTable::createSearchPredicateBuilder()` hook, and a custom builder of yours keeps
working.

## v0.82 → v0.83

Affects custom `QueryFilterInterface` implementations, code that implemented one of the removed
interfaces, and code that touched `DataTableInfrastructure`, the Ajax result types, or the internal
query/row-pipeline classes listed below. Table configuration, columns, Twig templates, the Ajax
routes, and every JSON payload on the wire are unchanged. If your project only configures tables
through `AbstractDataTable`, there is nothing to do.

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

### Mercure and API Platform resolver interfaces are removed

Each of these interfaces had exactly one implementation. Type-hint the concrete class instead; it is
no longer `final`, so a test double or a subclass of your own still works.

| Removed interface | Type-hint instead |
| --- | --- |
| `Mercure\MercureConfigResolverInterface` | `Mercure\MercureConfigResolver` |
| `Mercure\MercureHubUrlResolverInterface` | `Mercure\MercureHubUrlResolver` |
| `Mercure\ApiResourceMercureMetadataResolverInterface` | `ApiPlatform\ApiResourceMercureMetadataResolver` |
| `ApiPlatform\ApiResourceCollectionUrlResolverInterface` | `ApiPlatform\ApiResourceCollectionUrlResolver` |

The service aliases move with them, so an autowired argument or a service decoration targeting one
of the interfaces no longer resolves. Point it at the concrete class.

`MercurePublisherInterface` is untouched: it is the supported seam for replacing how updates are
published, and a custom publisher of yours keeps working.

### Ajax result and request DTOs are collapsed

`DetailRowResult` and `EditFormResult` were the same four-property type with the same factories, and
each Ajax controller rebuilt the JSON by hand. One result type now owns both.

```php
// before
use Pentiminax\UX\DataTables\Detail\DetailRowResult;
use Pentiminax\UX\DataTables\Form\EditFormResult;
use Pentiminax\UX\DataTables\Http\JsonErrorResponse;

$result = $detailRowService->handleView($dataTable, $id);          // DetailRowResult

if (!$result->success) {
    return JsonErrorResponse::create($result->message, $result->statusCode);
}

return new JsonResponse(['success' => true, 'html' => $result->html]);

// after
use Pentiminax\UX\DataTables\Ajax\AjaxActionResult;

$result = $detailRowService->handleView($dataTable, $id);          // AjaxActionResult

return $result->toJsonResponse();
```

What changed:

- `Detail\DetailRowResult` and `Form\EditFormResult` are replaced by `Ajax\AjaxActionResult`, with
  the same `success`, `html`, `message`, and `statusCode` properties and the same
  `success()`/`badRequest()`/`invalid()`/`notFound()`/`forbidden()` factories
- `DetailRowService::handleView()`, `EditFormService::handleView()`, and
  `EditFormService::handleSubmit()` return `AjaxActionResult`
- `Http\JsonErrorResponse` is removed. `AjaxActionResult::toJsonResponse()` builds the response,
  error cases included
- `Dto\AjaxDetailQueryDto` is renamed `Dto\AjaxEntityQueryDto`, and `Dto\AjaxEditFormQueryDto` and
  `Dto\AjaxDeleteRequestDto` are removed in favor of it. The three routes always mapped the same
  `{dataTable, id}` body. `Dto\AjaxEditRequestDto` and `Dto\AjaxEditFormRequestDto` are unchanged

The Ajax JSON is byte-for-byte what it was: same routes, same keys, same status codes. Frontend
code, including a custom fetch of your own against these routes, needs no change.

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
