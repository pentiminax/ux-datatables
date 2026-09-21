import type { BulkActionBar } from '../bulk/BulkActionBar.js'

let registered = false

export function registerBulkActionsFeature(DataTable: any): void {
    if (registered) {
        return
    }
    registered = true

    DataTable.feature.register('bulkActions', (settings: any, opts: any): HTMLElement => {
        const instance = opts?.instance as BulkActionBar | undefined

        if (!instance) {
            return document.createElement('div')
        }

        return instance.render(new DataTable.Api(settings))
    })
}
