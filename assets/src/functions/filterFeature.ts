import type { FilterBar } from './filters.js'

let registered = false

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

        const api = new DataTable.Api(settings)

        return instance.render(() => {
            const hasAjax = Boolean(
                settings?.ajax ||
                    settings?.sAjaxSource ||
                    settings?.oFeatures?.bServerSide ||
                    (typeof api.ajax?.url === 'function' && Boolean(api.ajax.url())) ||
                    (api.ajax && typeof api.ajax.reload === 'function' && typeof api.draw !== 'function')
            )

            if (hasAjax && api.ajax && typeof api.ajax.reload === 'function') {
                api.ajax.reload(null, true)
            } else if (typeof api.draw === 'function') {
                api.draw()
            }
            if (api.state && typeof api.state.save === 'function') {
                api.state.save()
            }
        })
    })
}
