<h1 align="center">UX DataTables</h1>

<p align="center">
    <strong>The easiest way to build DataTables.net tables in Symfony</strong>
</p>

<p align="center">
    <a href="https://packagist.org/packages/pentiminax/ux-datatables"><img src="https://img.shields.io/packagist/v/pentiminax/ux-datatables.svg?style=flat-square&label=stable" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/pentiminax/ux-datatables"><img src="https://img.shields.io/packagist/php-v/pentiminax/ux-datatables?style=flat-square" alt="PHP Version"></a>
    <a href="https://packagist.org/packages/pentiminax/ux-datatables/stats"><img src="https://img.shields.io/packagist/dt/pentiminax/ux-datatables.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://codecov.io/gh/pentiminax/ux-datatables"><img src="https://img.shields.io/codecov/c/github/pentiminax/ux-datatables?style=flat-square" alt="Coverage"></a>
</p>

<p align="center">
    <a href="https://pentiminax.github.io/ux-datatables/">Documentation</a>
     • <a href="demo/">Demo</a>
     • <a href="https://youtu.be/qYHRXr_qdPY">Video Tutorial</a>
</p>

UX DataTables is a [Symfony UX](https://ux.symfony.com) bundle that turns a PHP class into a
fully featured [DataTables.net](https://datatables.net) table: typed columns, Doctrine-backed
server-side processing, filters, actions, inline editing, and live updates.

## Quick Start

```bash
composer require pentiminax/ux-datatables
php bin/console make:datatable
```

Declare the table:

```php
use App\Entity\User;
use Pentiminax\UX\DataTables\Attribute\AsDataTable;
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

Handle it in a controller:

```php
#[Route('/users', name: 'app_users')]
public function index(UserDataTable $table, Request $request): Response
{
    $table->handleRequest($request);

    if ($table->isRequestHandled()) {
        return $table->getResponse();
    }

    return $this->render('user/index.html.twig', ['table' => $table]);
}
```

Render it in Twig:

```twig
{{ render_datatable(table) }}
```

That's it!

📖 **[Read the full documentation →](https://pentiminax.github.io/ux-datatables/)**

## Features

| Feature | Description |
| ------- | ----------- |
| ⚡ **Table in Minutes** | Scaffold a table from any Doctrine entity with `make:datatable` |
| 🗄️ **Server-side Processing** | Pagination, search, and ordering handled through Doctrine, in-memory arrays, or API Platform |
| 🎛️ **Typed Columns** | Text, number, money, date, boolean, enum, and more, configured fluently |
| 🔍 **Filters** | Built-in filters and column controls to narrow down your data |
| ⚙️ **Actions** | Row actions, bulk actions over selected rows, and detail rows |
| ✏️ **Inline Editing** | Edit rows in a modal or toggle booleans in place, with CSRF protection |
| 📤 **Exports** | CSV and XLSX exports through the same query pipeline |
| 🧩 **DataTables Extensions** | Buttons, Select, Responsive, FixedHeader, RowGroup, SearchBuilder, and more, lazy-loaded |
| 📡 **Live Updates** | Optional Mercure integration to push changes to the table |
| 🎨 **Bootstrap & Modals** | Style and modal adapters you can swap or extend |
| 🌍 **Translatable** | English and French catalogs included |
| ✅ **Testable** | `DataTableTestCase` exercises your table through the same Ajax routes as the browser |

## Requirements

- PHP 8.2 or higher
- Symfony 7.x or 8.x
- Symfony StimulusBundle 2.x or 3.x

## Security

The bundle registers Ajax routes under `/datatables/ajax/*` for data, edit, delete, detail, bulk
actions, and exports. The table token embedded in the page identifies **which** table is requested,
not **who** requests it. Protect these routes with your Symfony Security configuration:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/datatables/ajax, roles: ROLE_ADMIN }
```

See [Securing Ajax Routes](https://pentiminax.github.io/ux-datatables/getting-started/security/)
for details.

## Demo

A Symfony demo app in [`demo/`](demo/) tours columns, filters, actions, exports, and extensions,
each page next to the code running it:

```bash
cd demo
composer install
php bin/console app:demo:reset
php -S 127.0.0.1:8000 -t public
```

## Contributing

Contributions are welcome. Open an issue to discuss your idea first, then send a pull request.

## License

This bundle is released under the [MIT License](LICENSE).
