# Column summaries

Footer figures over every row matching the current search and filters, not only the visible page. Declare them on a column with `AbstractColumn::summarize(Summarizer ...$summarizers)`.

```php
use Pentiminax\UX\DataTables\Model\Summary\{Average, Count, Max, Min, Sum};

yield TextColumn::new('reference', 'Reference')->summarize(Count::new('Orders'));
yield MoneyColumn::new('total', 'Total')->currency('EUR')->summarize(Sum::new('Revenue'), Average::new());
yield NumberColumn::new('items', 'Items')->summarize(Min::new('Smallest'), Max::new('Largest'));
```

| Summarizer | `SummaryType` | Column |
|------------|---------------|--------|
| `Sum` | `Sum` (`'sum'`) | numeric |
| `Average` | `Average` (`'avg'`) | numeric |
| `Min` / `Max` | `Min` / `Max` | numeric |
| `Count` | `Count` (`'count'`) | any; non-null values |

- Each takes an optional label (`Sum::new('Revenue')`); the default is a translation key (`summary.sum`, `summary.avg`, ... in the `DataTables` domain). One summarizer per type per column.
- `summarize()` throws `InvalidArgumentException` on `TemplateColumn` / `ActionColumn`, and on `Sum`/`Average`/`Min`/`Max` for a non-numeric column (dates included; only `Count` applies).
- Nulls are ignored. `MoneyColumn` figures are formatted like its cells. Columns hidden by `permission()` never get a summary.

## Where it is computed

- Server-side Doctrine: one extra aggregate query from the same query as the rows (scope, search, filters; no paging/ordering). The response gains a `summaries` key only when a column declares one. The column must map a Doctrine field or a relation path; a virtual `mapRow()` column, a to-many path, or a composite identifier raises `LogicException`.
- Client-side: computed in the browser from the rows matching the search. A column combining `formatValueUsing()` with `Sum`/`Average`/`Min`/`Max` throws `LogicException` at render (the browser would sum text); use a server-side table or `Count` only.
- `ArrayDataProvider` aggregates in memory. API Platform tables cannot aggregate: `LogicException` at render.
- Custom provider: return the figures through the optional `$summaries` argument of `DataTableResult`.

```php
return new DataTableResult(
    recordsTotal: $total,
    recordsFiltered: $filtered,
    data: $rows,
    summaries: ['total' => ['sum' => 120, 'count' => 4]], // column name => summary type => value
);
```

A provider that cannot aggregate should implement `FeatureAwareDataProviderInterface` and return `false` for `DataSourceFeature::Summaries` (see `references/defining-a-datatable.md`).

`DECIMAL` sums come back as floats, so huge totals can lose precision. The bundle creates a `<tfoot>` when missing; style with `--dt-summary-surface`, `--dt-summary-border`, `--dt-summary-text`, `--dt-summary-text-muted`.
