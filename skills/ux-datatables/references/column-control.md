# ColumnControl

Read this reference when a task enables ColumnControl, changes per-column controls, uses
`searchList`, or handles ColumnControl search data on the server.

## Hook ownership

ColumnControl is configured through `AbstractDataTable`:

- Put table-wide controls and target rows in `configureDataTable()`.
- Put `setColumnControl()` and `disableColumnControl()` on columns returned by
  `configureColumns()`.
- Put actions-column overrides in `configureActions()`.

Show those hooks in generated PHP examples. A bare `$dataTable->columnControl()` fragment hides
where application code belongs.

## Enable and place controls

Calling `columnControl()` with no arguments keeps the bundle defaults: order controls in header row
0 and typed search controls in header row 1. Passing a target replaces those defaults with one
group. Use `ColumnControlExtension` directly when several target rows are needed.

```php
use App\Entity\Employee;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControlExtension;

#[AsDataTable(Employee::class)]
final class EmployeeDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->addExtension(
            (new ColumnControlExtension([]))
                ->add(0, ['order'])
                ->add('tfoot', ['search'])
        );
    }
}
```

Targets are header row indexes or `'tfoot'` / `'tfoot:1'`. ColumnControl creates missing target
rows. Passing `content` without a target throws. `setColumnControl()` needs the table-level
extension to be enabled or the frontend will not load the plugin.

## Per-column search lists

Use `SearchList` for finite value sets. The browser derives options from locally loaded rows when
no static options or Ajax provider is configured. Static options accept scalar lists,
`[label => value]` maps, DataTables `label` / `value` arrays, `BackedEnum::cases()`, or a backed enum
class. A Symfony-translatable enum is translated when a translator is available; otherwise labels
fall back to `getLabel()`, `label()`, then the case name.

```php
use App\Entity\Employee;
use App\Enum\EmploymentStatus;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;

#[AsDataTable(Employee::class)]
final class EmployeeDataTable extends AbstractDataTable
{
    public function configureDataTable(DataTable $table): DataTable
    {
        return $table->columnControl(target: 1, content: ['search']);
    }

    public function configureColumns(): iterable
    {
        yield TextColumn::new('name', 'Name');
        yield TextColumn::new('status', 'Status')->setColumnControl([
            [
                'target'  => 1,
                'content' => [SearchList::new()->options(EmploymentStatus::class)],
            ],
        ]);
    }
}
```

`SearchList` exposes `ajaxOnly()`, `hidable()`, `orthogonal()`, `search()`, `select()`, and
`title()`. It serializes only explicitly configured settings, leaving defaults to the installed
ColumnControl version. Static options and an Ajax provider are mutually exclusive.

## Ajax option providers

Use `SearchListOptionsProviderInterface` or a closure when a server-side table needs dynamic
options. The provider receives the normalized `DataTableRequest` and each permitted, searchable
column. Return `null` to omit a column, or an empty iterable to publish an empty list.

```php
use App\Entity\Employee;
use App\Repository\OfficeRepository;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Contracts\ColumnInterface;
use Pentiminax\UX\DataTables\Contracts\SearchListOptionsProviderInterface;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Extensions\ColumnControl\SearchList;

final class OfficeOptionsProvider implements SearchListOptionsProviderInterface
{
    public function __construct(private readonly OfficeRepository $offices)
    {
    }

    public function provide(DataTableRequest $request, ColumnInterface $column): ?iterable
    {
        return 'office' === $column->getName()
            ? $this->offices->labelValueOptions()
            : null;
    }
}

#[AsDataTable(Employee::class)]
final class EmployeeDataTable extends AbstractDataTable
{
    public function __construct(private readonly OfficeOptionsProvider $officeOptions)
    {
    }

    public function configureDataTable(DataTable $table): DataTable
    {
        return $table
            ->serverSide()
            ->processing()
            ->columnControl(
                target: 1,
                content: [SearchList::new()->ajaxOptionsProvider($this->officeOptions)],
            );
    }
}
```

The provider must apply the same tenant and authorization scope as the table query. Dynamic
providers run only when the bundle or a manual controller returns
`AbstractDataTable::getResponse()`. API Platform and external Ajax endpoints must add their own
`columnControl` response object. A server-side table never derives a complete list from its current
page.

## Custom content

Register custom JavaScript content on `datatables:pre-init`, after the extension bundle loads and
before DataTables constructs the table. `datatables:pre-connect` is too early and
`datatables:connect` is too late. Reference the registered name from a column in
`configureColumns()`, and enable ColumnControl in `configureDataTable()`.

## Invariants

- A column override wins for the same target; table-level groups on other targets remain active.
- `disableColumnControl()` disables every target for that column.
- Two different Ajax providers effective for the same column are a configuration error.
- UUID and ULID columns support equality and nullness searches, not partial `LIKE` logics.
- State saving, scrolling, FixedHeader, and FixedColumns remain native ColumnControl behavior.

See `docs/src/content/docs/extensions/column-control.mdx` for the complete user guide.
