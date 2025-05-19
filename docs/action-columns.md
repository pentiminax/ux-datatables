# Action columns

The `ActionColumn` class allows you to define a custom action column for your DataTables instance using the Pentiminax UX DataTables library.

## Table of Contents

* [Overview](#overview)
* [Usage](#usage)
* [Attributes](#attributes)
* [Example Integration with Stimulus](#example-integration-with-stimulus)
* [Serialized JSON Output](#serialized-json-output)
* [Requirements](#requirements)

---

## Overview

`ActionColumn` is an implementation of the `ColumnInterface` designed for representing actions (such as delete) inside a DataTable. It provides a convenient way to add buttons or links that perform specific operations on each row.

---

## Usage

```php
use Pentiminax\UX\DataTables\Model\ActionColumn;
use Pentiminax\UX\DataTables\Enum\Action;

$deleteColumn = ActionColumn::new(
    name: 'delete',
    title: 'Delete',
    action: Action::DELETE,
    actionLabel: 'Delete',
    actionUrl: '/api/resource/delete'
);
```

---

## Attributes

| Property      | Type          | Description                                    |
| ------------- | ------------- | ---------------------------------------------- |
| `name`        | `string`      | Internal identifier for the column.            |
| `title`       | `string`      | Displayed column header title.                 |
| `action`      | `Action` enum | Type of action (`DELETE`, etc.).               |
| `actionLabel` | `string`      | Text displayed inside the action button.       |
| `actionUrl`   | `string`      | Endpoint to call when the action is triggered. |