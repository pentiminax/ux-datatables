import type { DataTableView } from '../types/dataTableView.js'
import { isRecord } from './htmlUtils.js'

/**
 * Keeps SearchBuilder from offering what a server-side table refuses: the `without` condition and
 * columns flagged `searchable: false`. Client-side tables filter in the browser and are left alone.
 * User-provided conditions and column indexes are narrowed, not replaced.
 */
export function restrictSearchBuilder(payload: DataTableView): void {
    const raw = payload?.searchBuilder
    // PHP serializes a SearchBuilder with default options as `[]`.
    const options = Array.isArray(raw) && raw.length === 0 ? {} : raw
    if (payload?.serverSide !== true || !isRecord(options)) {
        return
    }
    payload.searchBuilder = options

    options.conditions = {
        ...options.conditions,
        array: { ...options.conditions?.array, without: null },
    }

    const offered: unknown = options.columns
    if (offered !== undefined && offered !== '*' && !Array.isArray(offered)) {
        return
    }

    const columns = payload.columns ?? []
    options.columns = columns.flatMap((column, index) =>
        column.searchable !== false && (!Array.isArray(offered) || offered.includes(index))
            ? [index]
            : []
    )
}
