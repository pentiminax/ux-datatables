# Usage

## Building a table in a controller

Inject the `DataTableBuilderInterface` service and build your table in PHP:

```php
// ...
use Pentiminax\UX\DataTables\Builder\DataTableBuilderInterface;
use Pentiminax\UX\DataTables\Column\TextColumn;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_homepage')]
    public function index(DataTableBuilderInterface $builder): Response
    {
        $table = $builder
            ->createDataTable('usersTable')
            ->columns([
                TextColumn::new('firstName', 'First name'),
                TextColumn::new('lastName', 'Last name'),
            ])
            ->data([
                [
                    'firstName' => 'John',
                    'lastName' => 'Doe',
                ],
                [
                    'firstName' => 'Jane',
                    'lastName' => 'Smith',
                ],
            ]);

        return $this->render('home/index.html.twig', [
            'table' => $table,
        ]);
    }
}
```

All options and data are passed as-is to DataTables. Refer to the
[DataTables documentation](https://datatables.net/manual/) for available
client-side options.

## Rendering in Twig

Once created in PHP, render the table in Twig:

```twig
{{ render_datatable(table) }}

{# You can pass HTML attributes as a second argument to add them on the <table> tag #}
{{ render_datatable(table, {'class': 'my-table'}) }}
```

## Extending the default behavior

Symfony UX DataTables lets you extend the default behavior using a custom
Stimulus controller:

```javascript
// mytable_controller.js

import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.element.addEventListener('datatables:pre-connect', this._onPreConnect);
        this.element.addEventListener('datatables:connect', this._onConnect);
    }

    disconnect() {
        // Always remove listeners when the controller is disconnected to avoid side effects
        this.element.removeEventListener('datatables:pre-connect', this._onPreConnect);
        this.element.removeEventListener('datatables:connect', this._onConnect);
    }

    _onPreConnect(event) {
        // The table is not yet created
        // You can access the config that will be passed to "new DataTable()"
        console.log(event.detail.config);

        // For instance, define a render callback for a given column
        event.detail.config.columns[0].render = function (data, type, row, meta) {
            return '<a href="' + data + '">Download</a>';
        }
    }

    _onConnect(event) {
        // The table was just created
        console.log(event.detail.table); // You can access the table instance using the event details

        // For instance you can listen to additional events
        event.detail.table.on('init', (event) => {
            /* ... */
        });
        event.detail.table.on('draw', (event) => {
            /* ... */
        });
    }
}
```

Then in your render call, add your controller as an HTML attribute:

```twig
{{ render_datatable(table, {'data-controller': 'mytable'}) }}
```
