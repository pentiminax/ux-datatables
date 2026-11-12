# Empty state and row presentation

Three additive `DataTable` methods; a table that does not call them renders as before.

```php
public function configureDataTable(DataTable $table): DataTable
{
    return $table
        ->serverSide()
        ->emptyState(
            heading: 'No invoices yet',
            description: 'Invoices appear here once you create your first order.',
            icon: Icon::Inbox,
            action: ['label' => 'Create an order', 'url' => '/orders/new'],
        )
        ->rowClass(static fn (Invoice $invoice): ?string => $invoice->isOverdue() ? 'table-danger' : null)
        ->recordUrl(fn (Invoice $invoice): string => $this->urlGenerator->generate('app_invoice_show', ['id' => $invoice->getId()]));
}
```

## `emptyState(string $heading, ?string $description = null, ?Icon $icon = null, ?array $action = null)`

- Shown only when there is no data at all. A search/filter that matches nothing shows the built-in "No results found" block (with a **Reset filters** button when the table has filters); texts come from `empty.noResults`, `empty.noResultsHint`, `empty.resetFilters`.
- Heading, description and action label are plain text, escaped; translate them yourself. `action['url']` must start with `/` or be `http(s)`, else `InvalidArgumentException`.

## `rowClass(callable $resolver)`

Receives the source row (the entity for Doctrine) and returns CSS classes for the `<tr>` (emitted as `DT_RowClass`), or `null`. Split on whitespace and de-duplicated; a class set in `mapRow()` is kept. Works for server-side, API Platform via the bundle's Ajax endpoint, and client-side `AbstractDataTable::rows()`. Does not apply when the browser reads a Hydra collection directly (no PHP row).

## `recordUrl(callable $resolver)`

Returns the URL a row navigates to on click (emitted as `data-record-url` via `DT_RowAttr`). Only `/path` or `http(s)` URLs are kept; `javascript:`, `data:`, `//host` etc. are dropped, and the browser re-checks. Clicks on links, buttons, inputs, selects, textareas, labels, `[role="button"]`, row actions and the selection cell keep their own behavior; Ctrl/Cmd opens a new tab.

- Not accessible by itself: the row is not focusable. Keep a real link in one cell (`UrlColumn` or a `TemplateColumn`).
- It is navigation, not authorization: the target route enforces its own access control.
