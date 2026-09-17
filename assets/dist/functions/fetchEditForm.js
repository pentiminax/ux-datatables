export async function fetchEditForm(payload) {
    const response = await fetch('/datatables/ajax/edit-form/view', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ dataTable: payload.dataTable, id: payload.id }),
    });
    return response.json();
}
//# sourceMappingURL=fetchEditForm.js.map