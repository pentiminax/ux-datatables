import { applyFeatureLayout } from './featureLayout.js'
import type { FilterBar } from './filters.js'

export interface FilterLayoutEntry {
    filters: { instance: FilterBar }
}

/**
 * Integrate the filter popover into the DataTables `layout` option.
 *
 * @deprecated since v1.1.1: `installFilterBar()` from `./filters.js` places the popover. Kept for
 * deep imports of `dist/functions/filterLayout.js`; it will be removed in v2.0.
 */
export function applyFilterLayout(payload: Record<string, any>, instance: FilterBar): void {
    applyFeatureLayout(
        payload,
        'filters',
        { filters: { instance } },
        {
            position: 'topEnd',
            before: ['search'],
        }
    )
}
