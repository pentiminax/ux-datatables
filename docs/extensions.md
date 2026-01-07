# Extensions

UX DataTables exposes common DataTables extensions through PHP helpers. You can
configure them on each table, or set defaults in the bundle configuration.

## Buttons

The Buttons extension adds export and interaction options (copy, CSV, Excel,
PDF, print, column visibility).

Learn more in the official docs: <https://datatables.net/extensions/buttons/>.

### Usage

```php
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\DataTable;

$buttonsExtension = new ButtonsExtension([
    ButtonType::COPY,
    ButtonType::CSV,
    ButtonType::EXCEL,
    ButtonType::PDF,
    ButtonType::PRINT,
]);

$datatable = new DataTable('example');
$datatable->extensions([$buttonsExtension]);
```

The Buttons extension automatically excludes columns marked as
`not-exportable`.

## Column Control

ColumnControl adds column-specific controls to the header and footer cells.
It ships with sensible defaults for ordering and per-column search.

```php
$datatable = new DataTable('example');
$datatable->columnControl();
```

## Select

Select adds item selection capabilities (rows, columns, or cells).

Learn more in the official docs: <https://datatables.net/extensions/select/>.

```php
use Pentiminax\UX\DataTables\Enum\SelectStyle;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;

$selectExtension = new SelectExtension(SelectStyle::MULTI);
$datatable = new DataTable('example');
$datatable->extensions([$selectExtension]);
```

You can further configure selection behavior (checkboxes, styles, item types)
via the `SelectExtension` constructor and helper methods.

## Responsive

Enable responsive layouts for smaller screens:

```php
$datatable->responsive();
```

## KeyTable

Enable keyboard navigation in the table (DataTables KeyTable extension):

```php
use Pentiminax\UX\DataTables\Model\Extensions\KeyTableExtension;

$datatable->addExtension(new KeyTableExtension());
```

## Scroller

Enable virtual scrolling for large datasets (DataTables Scroller extension):

```php
use Pentiminax\UX\DataTables\Model\Extensions\ScrollerExtension;

$datatable->addExtension(new ScrollerExtension());
```
