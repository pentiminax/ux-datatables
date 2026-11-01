/**
 * Keeps SearchBuilder from offering what a server-side table refuses: the `without` condition and
 * columns flagged `searchable: false`. Client-side tables filter in the browser and are left alone.
 * User-provided conditions and column indexes are narrowed, not replaced.
 */
export function restrictSearchBuilder(payload: Record<string, any>): void {
    const options = payload?.searchBuilder
    if (payload?.serverSide !== true || !options || typeof options !== 'object') {
        return
    }

    options.conditions = {
        ...options.conditions,
        array: { ...options.conditions?.array, without: null },
    }

    const offered: unknown = options.columns
    if (offered !== undefined && offered !== '*' && !Array.isArray(offered)) {
        return
    }

    const columns: Array<{ searchable?: boolean }> = payload.columns ?? []
    options.columns = columns.flatMap((column, index) =>
        column.searchable !== false && (!Array.isArray(offered) || offered.includes(index))
            ? [index]
            : []
    )
}
