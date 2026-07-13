export async function deleteEntity({ entity, id, topics, csrfToken, }) {
    const body = { entity, id: isNaN(Number(id)) ? id : Number(id) };
    if (topics && topics.length > 0) {
        body.topics = topics;
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