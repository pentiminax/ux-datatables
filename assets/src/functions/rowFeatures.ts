import type { DataTableView } from '../types/dataTableView.js'
import { applyEmptyState, bindEmptyStateReset } from './emptyState.js'
import { applyLocalLanguage } from './localLanguage.js'
import { bindRecordClick } from './recordUrl.js'

interface Resettable {
    resetFilters: () => void
}

interface SearchableTable {
    search: (value: string) => unknown
    columns?: () => { search: (value: string) => unknown }
}

/** The empty state overrides strings of the catalog, so it runs once the catalog is loaded. */
export async function applyTableLanguage(payload: DataTableView): Promise<void> {
    await applyLocalLanguage(payload)
    applyEmptyState(payload)
}

export function bindRowFeatures(
    payload: DataTableView,
    element: HTMLElement,
    getTable: () => SearchableTable | null,
    filterBar: Resettable | null
): void {
    if (payload.recordUrl === true) {
        bindRecordClick(element)
    }

    if (filterBar && payload.emptyState) {
        bindEmptyStateReset(element, () => {
            const table = getTable()

            table?.search('')
            table?.columns?.().search('')
            filterBar.resetFilters()
        })
    }
}
