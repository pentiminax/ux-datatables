import type { FilterBar } from './filters.js'


export interface FilterLayoutEntry {
    filters: { instance: FilterBar }
}

const FEATURE = 'filters'

function isFiltersMarker(value: unknown): boolean {
    if (value === FEATURE) return true
    if (value !== null && typeof value === 'object' && !Array.isArray(value)) {
        return FEATURE in (value as Record<string, unknown>)
    }
    return false
}

/**
 * Swap any `filters` marker the user placed in `layout` (via PHP, e.g.
 * `->layout(['topEnd' => [Feature::SEARCH, Feature::FILTERS]])`) for the runtime
 * entry carrying the live instance. Returns true when a marker was replaced.
 */
function replaceMarker(layout: Record<string, unknown>, entry: FilterLayoutEntry): boolean {
    let replaced = false

    for (const key of Object.keys(layout)) {
        const value = layout[key]

        if (isFiltersMarker(value)) {
            layout[key] = entry
            replaced = true
            continue
        }

        if (Array.isArray(value)) {
            layout[key] = value.map((item) => {
                if (isFiltersMarker(item)) {
                    replaced = true
                    return entry
                }
                return item
            })
        }
    }

    return replaced
}

/**
 * Integrate the filter popover into the DataTables `layout` option.
 */
export function applyFilterLayout(payload: Record<string, any>, instance: FilterBar): void {
    const entry: FilterLayoutEntry = { filters: { instance } }
    const layout = (payload.layout ??= {}) as Record<string, unknown>

    if (replaceMarker(layout, entry)) {
        return
    }

    const topEnd = layout.topEnd

    if (topEnd === undefined || topEnd === null) {
        layout.topEnd = ['search', entry]
    } else if (Array.isArray(topEnd)) {
        layout.topEnd = [...topEnd, entry]
    } else {
        layout.topEnd = [topEnd, entry]
    }
}
