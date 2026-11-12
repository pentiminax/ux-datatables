export const DEFAULT_ROW_ACTION_URLS = {
    delete: '/datatables/ajax/delete',
    toggle: '/datatables/ajax/edit',
    detail: '/datatables/ajax/detail',
    editForm: '/datatables/ajax/edit-form/view',
    editFormSubmit: '/datatables/ajax/edit-form',
}

export type RowActionName = keyof typeof DEFAULT_ROW_ACTION_URLS

export function rowActionUrl(payload: Record<string, any>, name: RowActionName): string {
    const url = payload?.rowActionUrls?.[name]

    return typeof url === 'string' && url.length > 0 ? url : DEFAULT_ROW_ACTION_URLS[name]
}
