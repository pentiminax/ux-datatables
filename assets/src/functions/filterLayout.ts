import { applyFeatureLayout } from './featureLayout.js'
import type { FilterBar } from './filters.js'

export interface FilterLayoutEntry {
    filters: { instance: FilterBar }
}

/**
 * Integrate the filter popover into the DataTables `layout` option.
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
