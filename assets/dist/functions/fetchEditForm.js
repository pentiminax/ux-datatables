export async function fetchEditForm(payload) {
    const params = new URLSearchParams({
        dataTable: payload.dataTable,
        id: payload.id,
    });
    const response = await fetch(`/datatables/ajax/edit-form?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    return response.json();
}
//# sourceMappingURL=fetchEditForm.js.map