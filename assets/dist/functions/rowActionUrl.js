export const DEFAULT_ROW_ACTION_URLS = {
    delete: '/datatables/ajax/delete',
    toggle: '/datatables/ajax/edit',
    detail: '/datatables/ajax/detail',
    editForm: '/datatables/ajax/edit-form/view',
    editFormSubmit: '/datatables/ajax/edit-form',
};
export function rowActionUrl(payload, name) {
    const url = payload?.rowActionUrls?.[name];
    return typeof url === 'string' && url.length > 0 ? url : DEFAULT_ROW_ACTION_URLS[name];
}
//# sourceMappingURL=rowActionUrl.js.map