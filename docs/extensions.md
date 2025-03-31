# Extensions

The DataTables library comes with a number of useful extensions.

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