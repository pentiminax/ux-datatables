import type { DataTableView } from '../types/dataTableView.js'

export type RowActionName = 'delete' | 'toggle' | 'detail' | 'editForm' | 'editFormSubmit'

export function rowActionUrl(payload: DataTableView, name: RowActionName): string {
    const url = payload.rowActionUrls?.[name]

    if (typeof url !== 'string' || url.length === 0) {
        throw new Error(`The payload has no "${name}" row action URL.`)
    }

    return url
}
