# Filters

Declarative filter popover (server-side Doctrine only). Declare filters in `configureFilters()`; the Stimulus controller renders a funnel toggle + badge next to the search box, opening a popover with the controls plus Apply/Reset; the table reloads over AJAX on apply, and each filter applies a Doctrine condition.

```php
use Doctrine\ORM\QueryBuilder;
use Pentiminax\UX\DataTables\Filter\{DateRangeFilter, Filter, ChoiceFilter, TernaryFilter, TextFilter};
use Pentiminax\UX\DataTables\Model\Filters;

public function configureFilters(Filters $filters): Filters
{
    return $filters
        ->add(TextFilter::new('name')->label('Name'))
        ->add(ChoiceFilter::new('status')->options(['Draft' => 'draft', 'Published' => 'published']))
        ->add(TernaryFilter::new('verified')->field('emailVerifiedAt')->trueLabel('Verified')->falseLabel('Not verified'))
        ->add(DateRangeFilter::new('createdAt'))
        ->add(Filter::new('vip')->query(
            fn (QueryBuilder $qb, mixed $value, string $alias) =>
                $qb->andWhere("$alias.score > :vip")->setParameter('vip', 100)
        ));
}
```

## Conventions

- `::new(string $name)` — name is the AJAX payload key (`filters[name]`).
- `->label()`, `->field()` (defaults to name, supports relations like `author.name`), `->placeholder()`.
- `->query(fn (QueryBuilder $qb, mixed $value, string $alias))` overrides the default condition.

## Types (`src/Filter/`)

| Class | Control | Default condition |
|-------|---------|-------------------|
| `TextFilter` | search input | `LOWER(field) LIKE %value%` |
| `ChoiceFilter` | select (`multiple()` for multi) | `field = value` / `field IN (...)`; `options()` accepts `[label => value]`, enum cases, or enum class-string |
| `TernaryFilter` | all/true/false select | `field IS [NOT] NULL`; `values($true, $false)` to compare concrete values |
| `DateRangeFilter` | two date inputs | `field >= from AND field <= to` (each bound optional) |
| `Filter` | checkbox | none — requires `query()`, runs only when checked |

## Flow

- Serialized into the Stimulus `view` payload under `filters` (only when non-empty), via `DataTable::setFilters()`.
- Frontend: registered as a custom DataTables feature `filters` (`assets/src/functions/filterFeature.ts` via `DataTable.feature.register`), placed in `layout` (`assets/src/functions/filterLayout.ts`) — default `topEnd` after `search`, or wherever `Feature::FILTERS` is positioned in `->layout()`. `assets/src/functions/filters.ts` builds the funnel toggle + popover, merges applied values into `ajax.data` (`filters[name]`), deferred reload on Apply/Reset. Default styles ship in `assets/dist/styles/datatables-style.css` (auto-imported, light/dark via CSS vars).
- Server-side: `DataTableRequest::filters` carries values; `AbstractDataTable::configureQueryBuilder()` applies each filter after the standard `QueryFilterChain`, so filtered count + page both reflect the filters. Empty/irrelevant values are no-ops.

## Gotchas

- Server-side only; no effect on client-side data tables in V1.
- A `query()` closure fully replaces the type's default behaviour.
- Filters preserve existing static `ajax.data` (e.g. from `ajaxRequestData()` / `mergeAjaxData()`).
