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

`ButtonType` cases: `COPY`, `CSV`, `EXCEL`, `PDF`, `PRINT`, `COLUMN_VISIBILITY` (value `'colvis'`), `COLUMN_CONTROL_SEARCH_CLEAR` (value `'ccSearchClear'`). The constructor also accepts strings or `Button` objects. Fluent helpers: `withCopyButton()`, `withCsvButton()`, `withExcelButton()`, `withPdfButton()`, `withPrintButton()`, `withColVisButton()`, `withCcSearchClearButton()`.

Fine-grained config via `Button`:
```php
new ButtonsExtension([
    Button::csv()->text('Export CSV')->className('btn btn-success')
        ->exportOptions(['columns' => ':visible'])->option('charset', 'utf-8'),
]);
```

To position buttons, place `Feature::BUTTONS` in `layout()` (see options).

`Button::ccSearchClear()` clears the global search plus every ColumnControl per-column search in
one click, using ColumnControl's own native Buttons entry (no JS needed). Requires
`ColumnControlExtension` on the table.

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

## FixedHeader — pin the header while scrolling

```php
new FixedHeaderExtension(header: true, footer: false, headerOffset: 0, footerOffset: 0);
```

Not intended to be combined with `ScrollerExtension` or the core `scrollY` / `scrollX` scrolling feature.

## ColReorder — drag to reorder columns

```php
new ColReorderExtension(enable: true, columns: '');  // both are defaults
```

`enable: false` loads ColReorder locked; toggle at runtime with the JS API
(`dt.colReorder.enable()`/`.disable()`, e.g. via `Button::custom()`). `columns` is a DataTables
column-selector string restricting which columns can be dragged.

## Responsive — collapse columns on small screens

```php
new ResponsiveExtension(auto: true, detailsTarget: 0, detailsType: 'inline', orthogonal: 'display');
```

All params optional — `$table->responsive()` uses every default. `detailsType` also accepts
`'column'`, `'colvis'`, or `false` to disable the hidden-column details control. Pass `breakpoints`
(list of `['name' => string, 'width' => int]`) to override DataTables' built-in list; omit to keep it.

## KeyTable — keyboard cell navigation

```php
new KeyTableExtension(blurable: true, className: 'focus', clipboard: true, columns: '', keys: null);
```

All params optional. `columns` is a column-selector string restricting which columns can be
focused. `focus` (`[row, column]`) and `keys` (key codes to listen for) default to `null`, omitted
from the payload unless set.

## Scroller — virtual scrolling for large tables

```php
new ScrollerExtension(boundaryScale: 0.5, displayBuffer: 9, rowHeight: 'auto', serverWait: 200);
```

All params optional and match DataTables' own defaults.

## Toggle-only extensions

No constructor args needed:

| Extension | Effect |
|-----------|--------|
| `ColumnControlExtension` | per-column order/search controls (`$table->columnControl()`) |

```php
$extensions->addExtension(new ColumnControlExtension());
```

See `docs/src/content/docs/extensions/combining-extensions.mdx` for compatible combinations.
