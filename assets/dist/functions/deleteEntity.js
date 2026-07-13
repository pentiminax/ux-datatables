export async function deleteEntity({ entity, id, dataTableClass, csrfToken, }) {
    const body = { entity, id: isNaN(Number(id)) ? id : Number(id) };
    if (dataTableClass) {
        body.dataTableClass = dataTableClass;
    }
    const headers = {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };
    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }
    return await fetch('/datatables/ajax/delete', {
        method: 'DELETE',
        headers,
        body: JSON.stringify(body),
    });
}
//# sourceMappingURL=deleteEntity.js.map