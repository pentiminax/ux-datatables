export async function fetchEditForm(payload) {
    const params = new URLSearchParams({
        entity: payload.entity,
        id: payload.id,
    });
    if (payload.dataTableClass) {
        params.append('dataTableClass', payload.dataTableClass);
    }
    const response = await fetch(`/datatables/ajax/edit-form?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    return response.json();
}
//# sourceMappingURL=fetchEditForm.js.map