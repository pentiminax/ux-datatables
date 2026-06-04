# Extensions

DataTables extensions live in `src/Model/Extensions/`. Add them by overriding `configureExtensions()` or fluently in `configureDataTable()` via `$table->addExtension(...)`. Two have dedicated shortcuts: `$table->responsive()` and `$table->columnControl()`.

```php
public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
{
    return $extensions
        ->addExtension(new ResponsiveExtension())
        ->addExtension(new ButtonsExtension([ButtonType::CSV, ButtonType::EXCEL]));
}
// or
public function configureDataTable(DataTable $table): DataTable
{
    return $table
        ->responsive()
        ->columnControl()
        ->addExtension(new ButtonsExtension([ButtonType::CSV]));
}
```

## Buttons — export / copy / column visibility

```php
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\{ButtonsExtension, Button};

new ButtonsExtension([
    ButtonType::COPY, ButtonType::CSV, ButtonType::EXCEL,
    ButtonType::PDF, ButtonType::PRINT, ButtonType::COLUMN_VISIBILITY,
]);
```

`ButtonType` cases: `COPY`, `CSV`, `EXCEL`, `PDF`, `PRINT`, `COLUMN_VISIBILITY` (value `'colvis'`). The constructor also accepts strings or `Button` objects. Fluent helpers: `withCopyButton()`, `withCsvButton()`, `withExcelButton()`, `withPdfButton()`, `withPrintButton()`, `withColVisButton()`.

Fine-grained config via `Button`:
```php
new ButtonsExtension([
    Button::csv()->text('Export CSV')->className('btn btn-success')
        ->exportOptions(['columns' => ':visible'])->option('charset', 'utf-8'),
]);
```

To position buttons, place `Feature::BUTTONS` in `layout()` (see options).

## Select — row/cell selection

```php
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;

(new SelectExtension(style: SelectStyle::MULTI))   // SINGLE | MULTI
    ->withCheckbox(true)
    ->headerCheckbox(true);
```
Constructor also exposes `blurable`, `className`, `info`, `items`, `keys`, `selector`, `toggleable`.

## FixedColumns — freeze columns

```php
new FixedColumnsExtension(start: 1, end: 0);  // freeze N leftmost / rightmost
```

## Toggle-only extensions

No constructor args needed:

| Extension | Effect |
|-----------|--------|
| `ResponsiveExtension` | collapse columns on small screens (`$table->responsive()`) |
| `ColumnControlExtension` | per-column order/search controls (`$table->columnControl()`) |
| `ScrollerExtension` | virtual scrolling for large tables |
| `KeyTableExtension` | keyboard cell navigation |
| `ColReorderExtension` | drag to reorder columns |

```php
$extensions
    ->addExtension(new ScrollerExtension())
    ->addExtension(new KeyTableExtension())
    ->addExtension(new ColReorderExtension());
```

See `docs/src/content/docs/extensions/combining-extensions.mdx` for compatible combinations.
