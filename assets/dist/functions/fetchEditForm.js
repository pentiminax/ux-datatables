export async function fetchEditForm(payload) {
    const response = await fetch('/datatables/ajax/edit-form/view', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({ dataTable: payload.dataTable, id: payload.id }),
    });
    const body = await response.json().catch(() => ({ success: false, html: '' }));
    return { ...body, response };
}
//# sourceMappingURL=fetchEditForm.js.map