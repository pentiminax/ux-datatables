# Upgrade Guide

## `AbstractDataTable` provider hook renamed

`AbstractDataTable` no longer uses `getDataProvider()` as the extension hook for manual providers.

Use `createDataProvider()` instead:

```php
use Pentiminax\UX\DataTables\Contracts\DataProviderInterface;
use Pentiminax\UX\DataTables\DataProvider\ArrayDataProvider;

protected function createDataProvider(): ?DataProviderInterface
{
    return new ArrayDataProvider($rows, $this->createRowMapper());
}
```

What changed:

- override `createDataProvider()` instead of `getDataProvider()`
- use `createRowMapper()` instead of `rowMapper()`
- `getDataProvider()` is now a public façade that returns the resolved provider
- `getDataTable()` is now the canonical rendering entrypoint; manual calls to `prepareForRendering()` are no longer required for normal rendering

## Unify extension configuration on `configureExtensions()`

`AbstractDataTable` no longer supports overriding `configureButtonsExtension()`,
`configureColumnControlExtension()`, or `configureSelectExtension()`.

Configure every extension from `configureExtensions()` instead:

```php
use Pentiminax\UX\DataTables\Enum\ButtonType;
use Pentiminax\UX\DataTables\Model\DataTableExtensions;
use Pentiminax\UX\DataTables\Model\Extensions\ButtonsExtension;
use Pentiminax\UX\DataTables\Model\Extensions\SelectExtension;

public function configureExtensions(DataTableExtensions $extensions): DataTableExtensions
{
    return $extensions
        ->addExtension(new ButtonsExtension([ButtonType::CSV]))
        ->addExtension(new SelectExtension());
}
```

If you previously customized Buttons, Column Control, or Select via the removed hooks,
migrate that logic into `configureExtensions()`.
