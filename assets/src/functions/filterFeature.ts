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
            if (api.ajax) {
                api.ajax.reload(null, true)
            } else {
                api.draw()
            }
            if (api.state && typeof api.state.save === 'function') {
                api.state.save()
            }
        })
    })
}
