# UX DataTables Demo

A small Symfony application that tours the features of
[UX DataTables](https://github.com/pentiminax/ux-datatables). Every page renders one table built only
with what the bundle ships, and shows the exact code running it.

The demo installs the bundle from this repository through a Composer `path` repository, so it always
runs the code of the branch you have checked out.

## Requirements

- PHP 8.4+ with the `pdo_sqlite` and `intl` extensions
- Composer

No database server, Node.js, or Docker is needed: data lives in SQLite and assets go through
AssetMapper.

## Run it

```bash
cd demo
composer install
php bin/console app:demo:reset
php -S 127.0.0.1:8000 -t public
```

Then open http://127.0.0.1:8000. With the [Symfony CLI](https://symfony.com/download), `symfony serve`
works too.

In the `dev` environment the Symfony web debug toolbar is enabled. Its **UX DataTables** panel lists
the tables a page rendered and the Ajax queries they served; open the profile of a
`/datatables/ajax/data` request to see the Doctrine queries behind a server-side page.

`app:demo:reset` rebuilds the database with 60 products, 500 customers, and 10,000 orders. The
**Reset data** button in the header does the same from the browser.

## What is inside

| Path | Content |
| --- | --- |
| `src/DataTable/` | One `AbstractDataTable` per page |
| `src/Demo/Pages.php` | The tour: page order, docs links, and the files shown in the code panel |
| `src/Entity/Customer.php` | Columns and filters declared with attributes |
| `src/Security/ProductVoter.php` | Row permissions for edit, delete, and detail actions |
| `tests/SmokeTest.php` | Renders every page and queries every server-side table |

## Security note

The demo has no login, so `config/packages/security.yaml` leaves `^/datatables/ajax` public. A real
application must restrict those routes to the users allowed to read and change the data. See
[Securing Ajax Routes](https://pentiminax.github.io/ux-datatables/getting-started/security/).
