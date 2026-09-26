import type { FilterBar } from './filters.js'

let registered = false

function hasAjaxSource(settings: any): boolean {
    return Boolean(settings?.ajax || settings?.oFeatures?.bServerSide)
}

export function registerFilterFeature(DataTable: any): void {
    if (registered) {
        return
    }
    registered = true

    DataTable.feature.register('filters', (settings: any, opts: any): HTMLElement => {
        const instance = opts?.instance as FilterBar | undefined
        if (!instance) {
            return document.createElement('div')
        }

        if (!hasAjaxSource(settings)) {
            console.warn(
                '[ux-datatables] Filters require an Ajax data source; enable serverSide() to use them.'
            )
            return document.createElement('div')
        }

        const api = new DataTable.Api(settings)

        return instance.render(() => api.ajax.reload(null, true))
    })
}
