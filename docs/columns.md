# Columns

## Introduction

In the UX DataTables library, the `Column` class allows for precise definition and configuration of table columns. Each column can be customized in terms of type, visibility, sorting, searching, and more.

## Creating a Column

To create a new column, use the static `new` method of the `Column` class:

```php
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;

$column = TextColumn::new('firstName', 'First Name');
```

Here, `'firstName'` is the internal name of the column, `'First Name'` is the title displayed in the table header, and `ColumnType::STRING` defines the data type of the column.

## Available Properties

The `Column` class provides several methods to configure column properties:

- **`setClassName(string $className): self`**: Sets the CSS class name to be applied to the column cells.
- **`setCellType(string $cellType): self`**: Specifies the cell type (`'td'` or `'th'`) to use for the column.
- **`setData(string $data): self`**: Sets the data source for the column.
- **`setOrderable(bool $orderable): self`**: Enables or disables sorting on this column.
- **`setSearchable(bool $searchable): self`**: Enables or disables searching on this column.
- **`setVisible(bool $visible): self`**: Determines whether the column is visible or not.
- **`setWidth(string $width): self`**: Specifies the column width (e.g., `'100px'`, `'10%'`).

## Example Usage with DataTable

Here's how to integrate columns into a `DataTable` instance:

```php
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Enum\ColumnType;

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

        $dataTable->add($nameColumn);
        $dataTable->add($ageColumn);

        return $dataTable;
    }
}
```

In this example, we create a table with two columns: one for the name and one for the age, each with specific configurations.

## Translating Column Titles

When your table extends `AbstractDataTable`, the Symfony translator is injected automatically through the `setTranslator()` method. You can therefore translate column titles at definition time and keep presentation logic inside the table class:

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

This keeps the raw translation keys out of your templates and guarantees that the header texts follow the current locale. Because the translation happens once during table construction, there is no runtime overhead when the table is rendered.

## Column Types

The library provides several column types through the `ColumnType` enumeration:

- **`ColumnType::DATE`**: For dates.
- **`ColumnType::NUM`**: For numbers.
- **`ColumnType::NUM_FMT`**: For formatted numbers.
- **`ColumnType::HTML`**: For HTML content.
- **`ColumnType::STRING`**: For string values.

The choice of column type influences sorting and searching behavior for that column.

## Converting to an Array

To retrieve the column configuration as an array (for example, for JSON serialization), use the `toArray` method:

```php
$configColumn = $nameColumn->toArray();
```

This method returns an associative array representing the column's properties, ready to be used in DataTables configuration options.

---

By properly configuring columns using the `Column` class, you can customize the behavior and appearance of your tables to meet the specific needs of your Symfony application.
