# Options

This document describes the PHP helpers available on `DataTable` to configure
DataTables options.

## Basic configuration

### autoWidth
Controls DataTables' smart column width handling.

```php
$dataTable->autoWidth(true);
```

### caption
Sets a caption for the table.

```php
$dataTable->caption('Monthly summary');
```

### order
Sets the initial ordering to apply to the table.

```php
$dataTable->order([
    [0, 'asc'],
]);
```

Each element can be:
- An array with `[column_index, direction]`
- An object with `{idx: number, dir: 'asc'|'desc'}`
- An object with `{name: string, dir: 'asc'|'desc'}`

## Data loading

### ajax
Loads data for the table's content from an Ajax source.

```php
use Pentiminax\UX\DataTables\Model\Options\AjaxOption;

$dataTable->ajax(new AjaxOption('/api/data'));
```

### data
Sets the display data for the table directly.

```php
$dataTable->data($rows);
```

## Features control

### deferRender
Controls deferred rendering for additional initialization speed.

```php
$dataTable->deferRender(true);
```

### info
Controls the information summary text display.

```php
$dataTable->info(false);
```

### lengthChange
Controls the user's ability to change the page length.

```php
$dataTable->lengthChange(false);
```

### ordering / withoutOrdering
Controls sorting capabilities. You can provide both handler and indicator flags
or disable ordering entirely.

```php
$dataTable->ordering(handler: true, indicators: true);
$dataTable->withoutOrdering();
```

### paging / withoutPaging
Controls pagination behavior. Use `paging()` to configure the buttons, or
`withoutPaging()` to disable paging altogether.

```php
$dataTable->paging(
    boundaryNumbers: true,
    buttons: 7,
    firstLast: true,
    numbers: true,
    previousNext: true,
);

$dataTable->withoutPaging();
```

### processing
Controls the processing indicator display.

```php
$dataTable->processing(true);
```

### searching
Controls global search/filtering.

```php
$dataTable->searching(true);
```

### serverSide
Enables server-side processing mode.

```php
$dataTable->serverSide(true);
```

### stateSave
Enables state saving so the table restores its state after reload.

```php
$dataTable->stateSave(true);
```

## Scrolling options

### scrollX
Enables horizontal scrolling.

```php
$dataTable->scrollX(true);
```

### scrollY
Enables vertical scrolling with a fixed height.

```php
$dataTable->scrollY('300px');
```

## Pagination

### displayStart
Defines the starting point for data display when using pagination.

```php
$dataTable->displayStart(20);
```

### lengthMenu
Sets the available page lengths.

```php
$dataTable->lengthMenu([10, 25, 50]);
```

### pageLength
Sets the initial page length.

```php
$dataTable->pageLength(25);
```

## Search options

### search
Sets the initial search string.

```php
$dataTable->search('priority');
```

### withSearchOption
Configures the full search option payload (regex, case sensitivity, delay, etc.).

```php
use Pentiminax\UX\DataTables\Model\Options\SearchOption;

$dataTable->withSearchOption(SearchOption::new(
    caseInsensitive: true,
    regex: false,
    return: false,
    search: 'priority',
    smart: true,
    searchDelay: 300,
));
```

## Internationalisation

### language
Sets the language options for DataTables using the `Language` enum.

```php
use Pentiminax\UX\DataTables\Enum\Language;

$dataTable->language(Language::FR);
```

## Layout

### layout
Defines where DataTables UI components should render.

```php
use Pentiminax\UX\DataTables\Enum\Feature;

$dataTable->layout(
    topStart: Feature::PAGE_LENGTH,
    topEnd: Feature::SEARCH,
    bottomStart: Feature::INFO,
    bottomEnd: Feature::PAGING,
);
```

## Example usage

```php
use Pentiminax\UX\DataTables\Enum\Feature;
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Options\AjaxOption;

$dataTable = new DataTable('example_table');

$dataTable
    ->autoWidth(true)
    ->caption('My Table')
    ->ordering(handler: true, indicators: true)
    ->paging(buttons: 5)
    ->searching(true)
    ->serverSide(false)
    ->scrollY('300px')
    ->layout(
        topStart: Feature::PAGE_LENGTH,
        topEnd: Feature::SEARCH,
        bottomStart: Feature::INFO,
        bottomEnd: Feature::PAGING,
    )
    ->ajax(new AjaxOption(
        url: '/api/data',
        dataSrc: 'data',
        type: 'POST'
    ));
```

## Styling DataTables

In your `assets/controllers.json` file, you should see a line that automatically
includes a CSS file for DataTables which will give you basic styles.

If you're using Bootstrap, set `datatables.net-dt/css/dataTables.dataTables.min.css`
to `false` and `datatables.net-bs5/css/dataTables.bootstrap5.min.css` to `true`:

```json
{
    "autoimport": {
        "datatables.net-dt/css/dataTables.dataTables.min.css": false,
        "datatables.net-bs5/css/dataTables.bootstrap5.min.css": true
    }
}
```
