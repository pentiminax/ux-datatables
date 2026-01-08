# Maker

Use Symfony MakerBundle to scaffold a DataTable class from a Doctrine entity.

## Requirements
- symfony/maker-bundle
- doctrine/orm (and DoctrineBundle)
- A Doctrine entity class

## Usage

```console
php bin/console make:datatable User
```

You can also pass a fully-qualified class name:

```console
php bin/console make:datatable App\\Entity\\User
```

The command creates a class in `App\DataTables`, for example:

```
src/DataTables/UserDataTable.php
```

The generated class uses `#[AsDataTable(Entity::class)]`, relies on the default
row mapper, and includes a starter `configureColumns()` method. Review the
columns and adjust types, titles, and order as needed.
