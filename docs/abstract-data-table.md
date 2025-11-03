# Use AbstractDataTable class

A concise, practical guide to implement data tables with configurable columns, optional extensions, and pluggable data providers (Doctrine or in‑memory), while avoiding stale data pitfalls in Symfony apps.

---

## Overview

`AbstractDataTable` wires a `DataTable` object (id, columns, and extensions). You provide a `DataProviderInterface` to fetch rows and a `RowMapperInterface` to transform domain objects into associative arrays for the frontend.

---

## Core Concepts

### `AbstractDataTable`

Base class you extend to declare table config and hook in optional extensions.

* `configureDataTable(DataTable $table): DataTable`
* `configureColumns(): iterable` → define column list, titles, and translations
* `configureExtensions(DataTableExtensions $extensions): DataTableExtensions`
* `configureButtonsExtension(ButtonsExtension $ext): ButtonsExtension`
* `configureColumnControlExtension(ColumnControlExtension $ext): ColumnControlExtension`
* `configureSelectExtension(SelectExtension $ext): SelectExtension`
* `getDataProvider(): ?DataProviderInterface`
* `fetchData(): void` → **call per request**
* `mapRow(mixed $item): array` → default object→array mapping (override as needed)
* `rowMapper(): RowMapperInterface` → returns a `ClosureRowMapper`

The class exposes a `$translator` property populated through Symfony's autowiring (`setTranslator()` is marked with `#[Required]`). Use it to translate column titles or button labels while configuring the table so that localization stays encapsulated in the table definition.

### `DataProviderInterface`

Abstraction to obtain rows.

```php
interface DataProviderInterface
{
    public function fetchData(DataTableQuery $query): DataTableResult;
}
```

### `DataTableQuery` / `DataTableResult`

Lightweight value objects representing the incoming query (pagination, search, orders, filters) and the resulting dataset (total, filtered, rows).

### `RowMapperInterface`

Maps each domain item (`object|array`) to an associative array consumable by the frontend.

We ship `ClosureRowMapper`, which accepts a `\Closure` mapping function.

---

## Doctrine Data Provider

```php
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\{DataProviderInterface, DataTableQuery, DataTableResult, RowMapperInterface};

final class DoctrineDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $entityClass,
        private readonly RowMapperInterface $rowMapper,
        /** @var null|callable(QueryBuilder, DataTableQuery):QueryBuilder */
        private readonly $queryBuilderConfigurator = null
    ) {}

    public function fetchData(DataTableQuery $query): DataTableResult
    {
        // build total, filtered, and page queries here, then map using $rowMapper
        // return new DataTableResult($recordsTotal, $recordsFiltered, $rowsIterable);
    }
}
```

**Usage in your table**

```php
final class UsersDataTable extends AbstractDataTable
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public function getDataProvider(): ?DataProviderInterface
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: \App\Entity\User::class,
            rowMapper: $this->rowMapper(),
            queryBuilderConfigurator: function (QueryBuilder $qb, DataTableQuery $q): QueryBuilder {
                return $qb
                    ->andWhere('e.birthDate > :birthDate')
                    ->setParameter('birthDate', '1999-01-01');
            }
        );
    }

    protected function mapRow(mixed $item): array
    {
        /** @var \App\Entity\User $item */
        return [
            'id'        => $item->getId(),
            'lastName'  => $item->getLastName(),
            'firstName' => $item->getFirstName(),
        ];
    }
}
```

---

## In‑Memory / Array Provider

For small datasets or tests, provide a simple array provider:

```php
final class ArrayDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly iterable $items,
        private readonly RowMapperInterface $rowMapper
    ) {}

    public function fetchData(DataTableQuery $query): DataTableResult
    {
        $all = is_array($this->items) ? $this->items : iterator_to_array($this->items);
        // apply search/order/pagination if needed
        $rows = (function () use ($all) {
            foreach ($all as $it) {
                yield $this->rowMapper->map($it);
            }
        })();

        return new DataTableResult(
            recordsTotal: count($all),
            recordsFiltered: count($all),
            rows: $rows
        );
    }
}
```

Usage:

```php
public function getDataProvider(): ?DataProviderInterface
{
    $rows = [
        ['id' => 1, 'lastName' => 'Doe', 'firstName' => 'John'],
        ['id' => 2, 'lastName' => 'Smith', 'firstName' => 'Anna'],
    ];

    return new ArrayDataProvider($rows, $this->rowMapper());
}
```

---

## Row Mapping

Use `ClosureRowMapper` when you want to keep mapping logic close to the table:

```php
protected function rowMapper(): RowMapperInterface
{
    return new ClosureRowMapper(function (mixed $item): array {
        if (is_object($item)) {
            return [
                'id' => $item->id,
                'name' => $item->name,
            ];
        }
        return (array) $item;
    });
}
```

Or override `mapRow()` directly in your table class.

---

## Server‑side vs Client‑side

* **Client‑side (non serverSide)**: call `fetchData()` once per request and return the full dataset in the initial payload.
* **Server‑side**: return only the requested page/filters from a dedicated endpoint that calls your provider and returns the DataTables JSON envelope.

---

## Full Example (Doctrine)

```php
final class UsersDataTable extends AbstractDataTable
{
    public function __construct(private EntityManagerInterface $em)
    {
        parent::__construct();
    }

    public function configureColumns(): iterable
    {
        return [
            ['name' => 'id', 'title' => 'ID', 'data' => 'id'],
            ['name' => 'lastName', 'title' => 'Last name', 'data' => 'lastName'],
            ['name' => 'firstName', 'title' => 'First name', 'data' => 'firstName'],
        ];
    }

    public function getDataProvider(): ?DataProviderInterface
    {
        return new DoctrineDataProvider(
            em: $this->em,
            entityClass: \App\Entity\User::class,
            rowMapper: $this->rowMapper()
        );
    }

    protected function mapRow(mixed $item): array
    {
        /** @var \App\Entity\User $item */
        return [
            'id'        => $item->getId(),
            'lastName'  => $item->getLastName(),
            'firstName' => $item->getFirstName(),
        ];
    }
}

final class UsersController
{
    public function __invoke(UsersDataTable $table, Request $request): JsonResponse
    {
        $query = DataTableQuery::fromRequest($request);

        if ($query->draw) {
            return $this->json(
                $table->fetchData($query)
            );
        }
    }
}
```

---

That’s it—fetch per request, map consistently, and keep persistence concerns behind providers. Happy shipping! 🚀
