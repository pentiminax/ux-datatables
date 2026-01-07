# AbstractDataTable

A practical guide to implementing data tables with configurable columns,
optional extensions, and pluggable data providers (Doctrine or in-memory)
while keeping server-side data fresh and consistent.

## Overview

`AbstractDataTable` wires a `DataTable` object (id, columns, and extensions).
You provide a `DataProviderInterface` to fetch rows and a `RowMapperInterface`
to transform domain objects into associative arrays for the frontend.

## Quick Start with #[AsDataTable]

For simple Doctrine-based tables, use the `#[AsDataTable]` attribute to
automatically configure a provider:

```php
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;
use Pentiminax\UX\DataTables\Column\TextColumn;
use App\Entity\User;

#[AsDataTable(User::class)]
final class UsersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        yield TextColumn::new('id', 'ID');
        yield TextColumn::new('lastName', 'Last name');
        yield TextColumn::new('firstName', 'First name');
    }

    protected function mapRow(mixed $item): array
    {
        /** @var User $item */
        return [
            'id' => $item->getId(),
            'lastName' => $item->getLastName(),
            'firstName' => $item->getFirstName(),
        ];
    }
}
```

The attribute automatically creates a `DoctrineDataProvider` with:
- your entity class
- the `rowMapper()` method (which wraps `mapRow()`)
- the `queryBuilderConfigurator()` method (for custom queries and filtering)

### Using queryBuilderConfigurator

The attribute automatically wires your `queryBuilderConfigurator()` method:

```php
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;

#[AsDataTable(entityClass: User::class)]
final class ActiveUsersDataTable extends AbstractDataTable
{
    public function queryBuilderConfigurator(QueryBuilder $qb, DataTableRequest $request): QueryBuilder
    {
        return $qb
            ->andWhere('e.active = :active')
            ->setParameter('active', true);
    }
}
```

### Manual Override

If you need custom logic, override `getDataProvider()` manually—it takes
precedence over the attribute:

```php
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;

#[AsDataTable(entityClass: User::class)] // This will be ignored
final class CustomUsersDataTable extends AbstractDataTable
{
    public function getDataProvider(): ?DataProviderInterface
    {
        return new ArrayDataProvider([], $this->rowMapper());
    }
}
```

## Core Concepts

### AbstractDataTable

Base class you extend to declare table configuration and hook in extensions.

- `configureDataTable(DataTable $table): DataTable`
- `configureColumns(): iterable`
- `configureExtensions(DataTableExtensions $extensions): DataTableExtensions`
- `configureButtonsExtension(ButtonsExtension $ext): ButtonsExtension`
- `configureColumnControlExtension(ColumnControlExtension $ext): ColumnControlExtension`
- `configureSelectExtension(SelectExtension $ext): SelectExtension`
- `getDataProvider(): ?DataProviderInterface`
- `fetchData(DataTableRequest $request): DataTableResult`
- `mapRow(mixed $item): array`
- `rowMapper(): RowMapperInterface`
- `handleRequest(Request $request): self`
- `isRequestHandled(): bool`
- `getResponse(): JsonResponse`

The class exposes a `$translator` property populated through Symfony's
autowiring (`setTranslator()` is marked with `#[Required]`). Use it to translate
column titles or button labels so localization stays encapsulated in the table
class.

### DataProviderInterface

Abstraction to obtain rows:

```php
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;

interface DataProviderInterface
{
    public function fetchData(DataTableRequest $request): DataTableResult;
}
```

### DataTableRequest / DataTableResult

`DataTableRequest` represents pagination, search, ordering, and column metadata.
`DataTableResult` wraps the data payload with total/filtered counts.

### RowMapperInterface

Maps each domain item (`object|array`) to an associative array consumable by the
frontend. The bundle ships with `ClosureRowMapper`, which accepts a `Closure`
mapping function.

## Doctrine Data Provider

```php
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Contracts\{DataProviderInterface, RowMapperInterface};
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Pentiminax\UX\DataTables\Model\DataTableResult;

final class DoctrineDataProvider implements DataProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $entityClass,
        private readonly RowMapperInterface $rowMapper,
        /** @var null|callable(QueryBuilder, DataTableRequest):QueryBuilder */
        private readonly $queryBuilderConfigurator = null
    ) {}

    public function fetchData(DataTableRequest $request): DataTableResult
    {
        // Build total, filtered, and page queries, then map using $rowMapper.
        // return new DataTableResult($recordsTotal, $recordsFiltered, $rowsIterable);
    }
}
```

## In-memory / Array Provider

For small datasets or tests, provide a simple array provider:

```php
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;

public function getDataProvider(): ?DataProviderInterface
{
    $rows = [
        ['id' => 1, 'lastName' => 'Doe', 'firstName' => 'John'],
        ['id' => 2, 'lastName' => 'Smith', 'firstName' => 'Anna'],
    ];

    return new ArrayDataProvider($rows, $this->rowMapper());
}
```

## Row Mapping

Use `ClosureRowMapper` when you want to keep mapping logic close to the table:

```php
use Pentiminax\UX\DataTables\RowMapper\ClosureRowMapper;

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

## Server-side vs Client-side

- **Client-side**: call `fetchData()` once per request and pass the data to the
  table before rendering.
- **Server-side**: return only the requested page/filters from a dedicated
  endpoint that calls your provider and returns the DataTables JSON envelope.

## Full Example (Doctrine)

```php
use Pentiminax\UX\DataTables\DataTableRequest\DataTableRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UsersDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [
            TextColumn::new('id', 'ID'),
            TextColumn::new('lastName', 'Last name'),
            TextColumn::new('firstName', 'First name'),
        ];
    }
}

final class UsersController
{
    public function __invoke(UsersDataTable $table, Request $request): Response
    {
        $table->handleRequest($request);

        if ($table->isRequestHandled()) {
            return $table->getResponse();
        }

        if (!$table->getDataTable()->isServerSide()) {
            $table->fetchData(DataTableRequest::fromRequest($request));
        }

        return $this->render('users/index.html.twig', [
            'table' => $table->getDataTable(),
        ]);
    }
}
```

Fetch per request, map consistently, and keep persistence concerns behind
providers.
