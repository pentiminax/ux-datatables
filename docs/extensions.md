# Extensions

The DataTables library comes with a number of useful extensions.

## Buttons

The Buttons extension provides additional export and interaction options for DataTables, such as copying data, exporting to different formats (CSV, Excel, PDF), and printing.

You can have more information about the Buttons extension [here](https://datatables.net/extensions/buttons/).

### Usage

To enable the Buttons extension, instantiate `ButtonsExtension` with the desired button types and add it to your DataTable:

```php
$buttonsExtension = new ButtonsExtension([
    ButtonType::COPY,
    ButtonType::CSV,
    ButtonType::EXCEL,
    ButtonType::PDF,
    ButtonType::PRINT
]);

$datatable = new DataTable('example');
$datatable->extensions([$buttonsExtension]);
```

### Button Types

The available button types are defined in the `ButtonType` enum:

```php
enum ButtonType: string
{
    case COPY = 'copy';
    case CSV = 'csv';
    case EXCEL = 'excel';
    case PDF = 'pdf';
    case PRINT = 'print';
}
```

## Select

Select adds item selection capabilities to a DataTable. Items can be rows, columns, or cells, which can be selected independently or together.

You can have more information about the Select extension [here](https://datatables.net/extensions/select/).

### Usage

To enable the Select extension, instantiate `SelectExtension` and add it to your DataTable:

```php
$selectExtension = new SelectExtension();
$datatable = new DataTable('example');
$datatable->extensions([$selectExtension]);
```

You can also specify a selection style:

```php
$selectExtension = new SelectExtension(SelectStyle::MULTI);
$datatable = new DataTable('example');
$datatable->extensions([$selectExtension]);
```
