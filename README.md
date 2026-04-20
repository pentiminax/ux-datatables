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
BooleanColumn::new('active')->renderAsSwitch();
```

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
        'table' => $table->getDataTable(),
    ]);
}
```

### 3. Render in Twig

```twig
{{ render_datatable(table) }}
```

> Tip: run `php bin/console make:datatable` to scaffold a DataTable class from any Doctrine entity.

## Documentation
- [Online documentation](https://pentiminax.github.io/ux-datatables/)
