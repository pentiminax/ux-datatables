# Polling

`DataTable::poll(string|int $interval)` reloads the table's Ajax source on a timer, keeping paging, search and ordering.

```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table->serverSide()->poll('30s');
}
```

- Interval: seconds (`30`) or `'10s'`, `'1m'`, `'1h'`; from 2 seconds to 24 hours. Outside the range or malformed throws `InvalidArgumentException`.
- Needs an Ajax source (server-side, `ajax()`, or API Platform). A prepared table with no `ajax` option throws `LogicException` at render, including one that sets the source only from JavaScript: declare it in PHP.
- A tick is skipped, not queued, while the tab is hidden (it reloads on return if the interval elapsed), a modal is open, a request is in flight, or the viewer is typing in the search box. The timer stops when the table is removed and restarts when Turbo restores it.
- Prefer Mercure (`references/mercure.md`) when the application knows when a row changes; poll for data changing from outside. Enabling both reloads on every push and tick (one console warning). Every tick hits the Ajax endpoint: keep the interval long.
