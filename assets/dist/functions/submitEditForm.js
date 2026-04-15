export async function submitEditForm(payload) {
    const response = await fetch('/datatables/ajax/edit-form', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            entity: payload.entity,
            id: payload.id,
            formData: payload.formData,
            topics: payload.topics ?? [],
            dataTableClass: payload.dataTableClass,
        }),
    });
    return response.json();
}
//# sourceMappingURL=submitEditForm.js.map