# Action columns

The `ActionColumn` class lets you define a custom action column for a DataTable
(such as delete or edit buttons).

## Overview

`ActionColumn` implements `ColumnInterface` and adds a structured payload for
front-end actions. It automatically marks the column as non-exportable by
assigning the `not-exportable` class.

> **Note**: The action column expects your dataset to contain an identifier that
> your front-end can use to call the provided `actionUrl`.

## Usage

```php
use Pentiminax\UX\DataTables\Column\ActionColumn;
use Pentiminax\UX\DataTables\Enum\Action;

$deleteColumn = ActionColumn::new(
    name: 'delete',
    title: 'Delete',
    action: Action::DELETE,
    actionLabel: 'Delete',
    actionUrl: '/api/resource/delete'
);
```

## Attributes

| Property      | Type     | Description                                    |
| ------------- |----------| ---------------------------------------------- |
| `name`        | `string` | Internal identifier for the column.            |
| `title`       | `string` | Displayed column header title.                 |
| `action`      | `enum`   | Type of action (`DELETE`, etc.).               |
| `actionLabel` | `string` | Text displayed inside the action button.       |
| `actionUrl`   | `string` | Endpoint to call when the action is triggered. |
