# Ajax

## Introduction

The `ajax` option lets DataTables load data from an HTTP endpoint. This is ideal
for large datasets or server-side filtering.

## Using with Symfony

```php
use Pentiminax\UX\DataTables\Model\DataTable;
use Pentiminax\UX\DataTables\Model\Options\AjaxOption;

class MyTableService
{
    public function createTable(): DataTable
    {
        $dataTable = new DataTable('example_table');

        /**
         * url: string // API endpoint to fetch data
         * dataSrc: string // Key in the JSON response containing the data (optional)
         * type: string // HTTP method used (default is GET)
         */
        $dataTable->ajax(url: '/api/data', dataSrc: 'data', type: 'POST');

        return $dataTable;
    }
}
```

## Expected JSON Response Format

The server should return a JSON response containing:

```json
{
  "data": [
    { "id": 1, "name": "Product A", "price": 10.5 },
    { "id": 2, "name": "Product B", "price": 20.0 }
  ],
  "recordsTotal": 100,
  "recordsFiltered": 100,
  "draw": 1
}
```

- `data`: Array containing the rows to display.
- `recordsTotal`: Total number of records in the dataset.
- `recordsFiltered`: Total number of records after filtering.
- `draw`: Draw counter from the request (DataTables uses it to protect against
  out-of-order responses).

## Mapping Columns to JSON Keys

`TextColumn::new()` automatically uses the column name as the data key. Call
`setData()` only when the JSON key differs:

```php
use Pentiminax\UX\DataTables\Column\TextColumn;

TextColumn::new('name', 'Name');
TextColumn::new('price', 'Price')->setData('price.amount');
```

## Building Responses in Symfony

To generate the expected response format, use `DataTableResponseBuilder`:

```php
use Pentiminax\UX\DataTables\Builder\DataTableResponseBuilder;

$responseBuilder = new DataTableResponseBuilder();

return $responseBuilder->buildResponse(
    draw: 1,
    data: $data,
    recordsTotal: $totalRecords,
    recordsFiltered: $filteredRecords
);
```

## Error Handling

If the server returns an error, send a JSON response with an `error` key so
DataTables can display the message:

```json
{
  "error": "An error occurred while loading the data."
}
```
