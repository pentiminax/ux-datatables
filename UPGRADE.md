# Upgrade Guide

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
