import { applyFeatureLayout } from './featureLayout.js'
import type { FilterBar } from './filters.js'

export interface FilterLayoutEntry {
    filters: { instance: FilterBar }
}

/**
 * @deprecated since 1.2, use installFilterBar() from './filters.js' instead. Removed in 2.0.
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
