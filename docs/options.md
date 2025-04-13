# Options

This document describes all available options for configuring your DataTable instance.

## Basic Configuration

### autoWidth
Controls DataTables' smart column width handling.
```php
$dataTable->autoWidth(bool $autoWidth)
```

### caption
Sets a caption for the table.
```php
$dataTable->caption(string $caption)
```

### order
Sets the initial order (sort) to apply to the table.
```php
$dataTable->order(array $order)
```
The order array can contain:
- An array with [column_index, direction]
- An object with {idx: number, dir: 'asc'|'desc'}
- An object with {name: string, dir: 'asc'|'desc'}

## Data Loading

### ajax
Loads data for the table's content from an Ajax source.
```php
$dataTable->ajax(AjaxOptions $ajaxOption)
```

### data
Sets the display data for the table directly.
```php
$dataTable->data(array $data)
```

## Features Control

### deferRender
Controls deferred rendering for additional speed of initialization.
```php
$dataTable->deferRender(bool $deferRender)
```

### info
Controls the table information display field.
```php
$dataTable->info(bool $info)
```

### lengthChange
Controls the end user's ability to change the paging display length.
```php
$dataTable->lengthChange(bool $lengthChange)
```

### ordering
Controls sorting abilities in DataTables.
```php
$dataTable->ordering(bool $ordering)
```

### paging
Enables or disables table pagination.
```php
$dataTable->paging(bool $paging)
```

### processing
Controls the processing indicator display.
```php
$dataTable->processing(bool $processing)
```

### searching
Controls search (filtering) abilities.
```php
$dataTable->searching(bool $searching)
```

### serverSide
Enables server-side processing mode.
```php
$dataTable->serverSide(bool $serverSide)
```

### stateSave
Enables state saving - allows the table to restore its state when reloaded.
```php
$dataTable->stateSave(bool $stateSave)
```

## Scrolling Options

### scrollX
Enables horizontal scrolling.
```php
$dataTable->scrollX(bool $scrollX)
```

### scrollY
Enables vertical scrolling with a fixed height.
```php
$dataTable->scrollY(string $scrollY)
```

## Pagination

### displayStart
Defines the starting point for data display when using pagination.
```php
$dataTable->displayStart(int $displayStart)
```

## Initial search

### search
Sets the initial search value for the table.
```php
$dataTable->search(string $search)
``` 

## Internationalisation

### language

Sets the language options for DataTables.
```php
$dataTable->language(Langage $language)
```

## Example Usage

```php
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\AjaxOptions;

$dataTable = new DataTable('example_table');

$dataTable
    ->autoWidth(true)
    ->caption('My Table')
    ->ordering(true)
    ->paging(true)
    ->searching(true)
    ->serverSide(false)
    ->scrollY('300px')
    ->ajax(new AjaxOptions(
        url: '/api/data',
        dataSrc: 'data',
        type: 'POST'
    ));
```

This example creates a DataTable with common options configured, including Ajax data loading, scrolling, and basic features enabled.

## Styling DataTables

In your ``assets/controllers.json`` file, you should see a line that automatically 
includes a CSS file for DataTables which will give you basic styles.

If you're using Bootstrap, set ``datatables.net-dt/css/dataTables.dataTables.min.css`` to false 
and ``datatables.net-bs5/css/dataTables.bootstrap5.min.css`` to true:

```json
{
    "autoimport": {
        "datatables.net-dt/css/dataTables.dataTables.min.css": false,
        "datatables.net-bs5/css/dataTables.bootstrap5.min.css": true
    }
}
```