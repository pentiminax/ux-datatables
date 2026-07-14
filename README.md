# UX DataTables

[![Latest Stable Version](https://img.shields.io/packagist/v/pentiminax/ux-datatables.svg?style=flat-square)](https://packagist.org/packages/pentiminax/ux-datatables)
[![PHP Version](https://img.shields.io/packagist/php-v/pentiminax/ux-datatables?style=flat-square)](https://packagist.org/packages/pentiminax/ux-datatables)
[![Downloads total](https://img.shields.io/packagist/dt/pentiminax/ux-datatables.svg?style=flat-square)](https://packagist.org/packages/pentiminax/ux-datatables/stats)
[![Coverage](https://img.shields.io/codecov/c/github/pentiminax/ux-datatables?style=flat-square)](https://codecov.io/gh/pentiminax/ux-datatables)

UX DataTables is a Symfony bundle integrating the [DataTables][1]
library in Symfony applications.

[Video tutorial](https://youtu.be/qYHRXr_qdPY)

[1]: https://datatables.net

## Requirements
- PHP 8.3 or higher
- Symfony StimulusBundle (installed through Symfony UX)
- Composer

## Installation

Install the library via Composer:

```console
composer require pentiminax/ux-datatables
```

## Usage

### 1. Declare a DataTable

```php
use App\Entity\User;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
use Pentiminax\UX\DataTables\Column\BooleanColumn;
use Pentiminax\UX\DataTables\Column\DateColumn;
use Pentiminax\UX\DataTables\Column\MoneyColumn;
use Pentiminax\UX\DataTables\Column\NumberColumn;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Pentiminax\UX\DataTables\Model\AbstractDataTable;

#[AsDataTable(User::class)]
final class UserDataTable extends AbstractDataTable
{
    public function configureColumns(): iterable
    {
        return [
            NumberColumn::new('id', 'ID'),
            TextColumn::new('firstName', 'First name'),
            TextColumn::new('email', 'Email'),
            DateColumn::new('createdAt', 'Created at'),
        ];
    }
}
```

Column variants are configured fluently after `new()`:

```php
TextColumn::new('name')->utf8();
TextColumn::new('content')->html()->utf8();
NumberColumn::new('price')->formatted();
MoneyColumn::new('price')->currency('EUR')->storedAsCents();
BooleanColumn::new('active')->renderAsSwitch();
TextColumn::new('internalCode')->disableColumnControl();
```

`disableColumnControl()` removes all ColumnControl controls for the column without disabling
DataTables search processing.

### 2. Wire it in a controller

```php
#[Route('/users', name: 'app_users')]
public function index(UserDataTable $table, Request $request): Response
{
    $table->handleRequest($request);

    if ($table->isRequestHandled()) {
        return $table->getResponse();
    }

    return $this->render('user/index.html.twig', [
        'table' => $table,
    ]);
}
```

### 3. Render in Twig

```twig
{{ render_datatable(table) }}
```

> Tip: run `php bin/console make:datatable` to scaffold a DataTable class from any Doctrine entity.

## Security

The bundle auto-registers a set of Ajax routes under `/datatables/ajax/*` (`ux_datatables_ajax_data`,
`ux_datatables_ajax_delete`, `ux_datatables_ajax_edit`, `ux_datatables_ajax_edit_form`,
`ux_datatables_ajax_edit_form_submit`, `ux_datatables_ajax_detail`, `ux_datatables_ajax_templates`).

The table token embedded in the rendered HTML identifies **which** table is requested, not **who** is
requesting it — it is **not** a user-authentication or per-user authorization mechanism. If a table is
displayed behind a firewall but these routes are left unprotected, the underlying data (and the
edit/delete actions) can be reached by anyone holding the token.

Protect the routes with an `access_control` rule that matches the pages rendering your tables:

```yaml
# config/packages/security.yaml
security:
    access_control:
        # access_control is first-match-wins; place this before any broader rule.
        - { path: ^/datatables/ajax, roles: ROLE_ADMIN }
```

Delete actions and inline boolean toggles additionally require an active session for CSRF
protection. Without one, their controls are rendered disabled (`mutationsEnabled: false` in the
table payload).

See [Securing Ajax Routes](https://pentiminax.github.io/ux-datatables/getting-started/security/) for
details.

## Documentation
- [Online documentation](https://pentiminax.github.io/ux-datatables/)
