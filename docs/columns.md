# Columns

## Introduction

In the UX DataTables library, each column is represented by a `Column` class
that lets you configure how the table renders, sorts, searches, and exports its
data.

## Creating a Column

Use the static `new()` method on the column type you need:

```php
use Pentiminax\UX\DataTables\Column\TextColumn;

$column = TextColumn::new('firstName', 'First Name');
```

The `new()` factory sets the column name, title, data source, and type. The data
source defaults to the column name, so you only need `setData()` when the JSON
key differs.

## Common Configuration Methods

All concrete columns inherit the following methods from `AbstractColumn`:

- **`setClassName(?string $className): self`**: Adds a CSS class to the column
  cells.
- **`setCellType(?string $cellType): self`**: Defines the HTML cell tag (`td`
  or `th`).
- **`setData(?string $data): self`**: Sets the JSON key used for the column.
- **`setDefaultContent(?string $defaultContent): self`**: Fallback content when
  the cell data is null.
- **`setOrderable(bool $orderable = true): self`**: Enables or disables sorting
  on this column.
- **`setSearchable(bool $searchable = true): self`**: Enables or disables
  searching on this column.
- **`setRender(?string $render): self`**: Registers a JavaScript render
  callback (stringified function name or body).
- **`setTitle(string $title): self`**: Sets the header label.
- **`setVisible(bool $visible = true): self`**: Controls visibility in the
  table.
- **`setWidth(?string $width): self`**: Defines the column width (e.g. `100px`
  or `10%`).
- **`setExportable(bool $exportable = true): self`**: Controls export behavior.
  Non-exportable columns automatically receive the `not-exportable` class.

## Example Usage with a DataTable

```php
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;

class MyTableService
{
    public function __construct(
        private DataTableBuilderInterface $builder,
    ) {
    }

    public function createDataTable(): DataTable
    {
        $dataTable = $this->builder->createDataTable('example_table');

        $nameColumn = TextColumn::new('name', 'Name')
            ->setClassName('col-name')
            ->setOrderable(true)
            ->setSearchable(true);

        $ageColumn = NumberColumn::new('age', 'Age')
            ->setWidth('50px')
            ->setOrderable(true)
            ->setSearchable(false);

        $dataTable->columns([$nameColumn, $ageColumn]);

        return $dataTable;
    }
}
```

## Translating Column Titles

When your table extends `AbstractDataTable`, the Symfony translator is injected
through `setTranslator()`. Translate titles during configuration to keep
presentation logic within the table:

```php
use Pentiminax\UX\DataTables\Column\TextColumn;

final class UsersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('name')
            ->setTitle($this->translator->trans('datatable.columns.name', domain: 'messages'));
    }
}
```

## Column Types

The `ColumnType` enum controls how DataTables sorts and searches columns. Common
values include:

- **`ColumnType::DATE`**: Date values.
- **`ColumnType::NUM`**: Numeric values.
- **`ColumnType::NUM_FMT`**: Formatted numbers.
- **`ColumnType::HTML`**: HTML content.
- **`ColumnType::STRING`**: Text content.

## Converting a Column to an Array

If you need the DataTables configuration array directly, call `jsonSerialize()`:

```php
$configColumn = $nameColumn->jsonSerialize();
```

This returns the full configuration ready to be included in DataTables options.
